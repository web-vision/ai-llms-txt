<?php

declare(strict_types=1);

namespace WebVision\AiLlmsTxt\Repository;

use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Context\LanguageAspectFactory;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\Database\Query\Restriction\FrontendRestrictionContainer;
use TYPO3\CMS\Core\Domain\Repository\PageRepository as CorePageRepository;
use TYPO3\CMS\Core\Site\Entity\SiteLanguage;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Repository for fetching page data
 * Handles all database queries related to pages using the Repository pattern
 * Uses Core's PageRepository for proper language fallback handling
 */
class PageRepository
{
    /**
     * Maximum recursion depth to prevent infinite loops from corrupted data
     */
    private const MAX_RECURSION_DEPTH = 50;

    /**
     * Maximum pages to load in a single batch query (performance limit)
     */
    private const MAX_BATCH_SIZE = 1000;

    /**
     * Doktypes that should be excluded but their children should still be fetched
     */
    private const EXCLUDED_DOKTYPES = [
        CorePageRepository::DOKTYPE_SYSFOLDER,
        CorePageRepository::DOKTYPE_SPACER,
        CorePageRepository::DOKTYPE_SHORTCUT,
    ];

    /**
     * Fields to select for navigation queries
     */
    private const NAVIGATION_FIELDS = [
        'uid',
        'pid',
        'title',
        'description',
        'abstract',
        'nav_title',
        'doktype',
        'sys_language_uid',
        'l10n_parent',
    ];

    public function __construct(
        private readonly ConnectionPool $connectionPool,
        private readonly Context $context
    ) {
    }

    /**
     * Find a page by its UID
     *
     * @return array{uid: int, title: string, subtitle: string, seo_title: string, description: string, abstract: string}|array{}
     */
    public function findById(int $pageId, ?SiteLanguage $siteLanguage = null): array
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('pages');
        $queryBuilder->getRestrictions()->removeAll()
            ->add(GeneralUtility::makeInstance(FrontendRestrictionContainer::class));

        $result = $queryBuilder
            ->select('uid', 'pid', 'title', 'subtitle', 'seo_title', 'description', 'abstract', 'sys_language_uid', 'l10n_parent')
            ->from('pages')
            ->where(
                $queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($pageId, Connection::PARAM_INT))
            )
            ->executeQuery();

        $page = $result->fetchAssociative();
        if (!$page) {
            return [];
        }

        if ($siteLanguage && $siteLanguage->getLanguageId() > 0) {
            $corePageRepository = $this->createCorePageRepository($siteLanguage);
            // getPagesOverlay processes an array of row data
            $overlaidPages = $corePageRepository->getPagesOverlay([$page]);
            if (!empty($overlaidPages[0])) {
                $page = $overlaidPages[0];
            }
        }

        return $page;
    }

    /**
     * Find navigation pages by parent UID (all languages)
     *
     * Note: For language-aware fetching with proper fallback handling,
     * use findNavigationByParentWithFallback() instead.
     *
     * @param int $parentUid Parent page UID
     * @param array<int, int> $excludeDoktypes Additional doktypes to exclude, on top of the
     *   always-excluded structural doktypes (sysfolder/spacer/shortcut)
     * @return array<int, array{uid: int, pid: int, title: string, description: string, abstract: string, doktype: int, sys_language_uid: int, l10n_parent: int}>
     */
    public function findNavigationByParent(int $parentUid, array $excludeDoktypes = []): array
    {
        return $this->findNavigationPages($parentUid, null, $excludeDoktypes, []);
    }

    /**
     * Find navigation pages by parent UID filtered by language
     *
     * @param int $parentUid Parent page UID
     * @param int $languageUid Language UID to filter by
     * @return array<int, array{uid: int, pid: int, title: string, description: string, abstract: string, doktype: int, sys_language_uid: int, l10n_parent: int}>
     *
     * @deprecated since 0.3.0, will be removed in 1.0.0. Use findNavigationByParentWithFallback() with SiteLanguage for proper language fallback handling.
     */
    public function findNavigationByParentAndLanguage(int $parentUid, int $languageUid = 0): array
    {
        return $this->findNavigationPages($parentUid, $languageUid, [], []);
    }

    /**
     * Internal method to find navigation pages with recursion protection
     *
     * @param int $parentUid Parent page UID
     * @param int|null $languageUid Language UID to filter by (null = all languages)
     * @param array<int, int> $excludeDoktypes Additional doktypes to exclude
     * @param array<int, bool> $visitedUids UIDs already visited to prevent infinite recursion
     * @param int $currentDepth Current recursion depth
     * @return array<int, array{uid: int, pid: int, title: string, description: string, abstract: string, doktype: int, sys_language_uid: int, l10n_parent: int}>
     */
    protected function findNavigationPages(
        int $parentUid,
        ?int $languageUid,
        array $excludeDoktypes,
        array $visitedUids,
        int $currentDepth = 0
    ): array {
        // Prevent infinite recursion
        if ($currentDepth >= self::MAX_RECURSION_DEPTH) {
            return [];
        }

        if (isset($visitedUids[$parentUid])) {
            return [];
        }
        $visitedUids[$parentUid] = true;

        $excludedSet = array_merge(self::EXCLUDED_DOKTYPES, $excludeDoktypes);

        $queryBuilder = $this->createNavigationQueryBuilder($parentUid, $languageUid);
        $result = $queryBuilder->executeQuery();

        $pages = [];
        while ($row = $result->fetchAssociative()) {
            // Skip excluded doktypes (structural + configured) but fetch their subpages
            if (in_array((int)$row['doktype'], $excludedSet, true)) {
                // Recursively get children of skipped pages
                $childPages = $this->findNavigationPages(
                    (int)$row['uid'],
                    $languageUid ?? (int)$row['sys_language_uid'],
                    $excludeDoktypes,
                    $visitedUids,
                    $currentDepth + 1
                );
                foreach ($childPages as $childPage) {
                    $pages[] = $childPage;
                }
                continue;
            }

            $pages[] = $this->mapRowToPageArray($row);
        }

        return $pages;
    }

    /**
     * Create a query builder for navigation queries
     */
    protected function createNavigationQueryBuilder(int $parentUid, ?int $languageUid): QueryBuilder
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('pages');

        $queryBuilder->getRestrictions()->removeAll()
            ->add(GeneralUtility::makeInstance(FrontendRestrictionContainer::class));

        $queryBuilder
            ->select(...self::NAVIGATION_FIELDS)
            ->from('pages')
            ->where(
                $queryBuilder->expr()->eq('pid', $queryBuilder->createNamedParameter($parentUid, Connection::PARAM_INT)),
                $queryBuilder->expr()->eq('nav_hide', $queryBuilder->createNamedParameter(0, Connection::PARAM_INT)),
                $queryBuilder->expr()->eq('no_index', $queryBuilder->createNamedParameter(0, Connection::PARAM_INT))
            )
            ->orderBy('sorting');

        // Add language filter if specified
        if ($languageUid !== null) {
            $queryBuilder->andWhere(
                $queryBuilder->expr()->eq('sys_language_uid', $queryBuilder->createNamedParameter($languageUid, Connection::PARAM_INT))
            );
        }

        return $queryBuilder;
    }

    /**
     * Map a database row to a page array
     *
     * @return array{uid: int, pid: int, title: string, description: string, abstract: string, doktype: int, sys_language_uid: int, l10n_parent: int}
     */
    protected function mapRowToPageArray(array $row): array
    {
        return [
            'uid' => (int)$row['uid'],
            'pid' => (int)$row['pid'],
            'title' => $row['nav_title'] ?: $row['title'],
            'description' => $row['description'] ?? '',
            'abstract' => $row['abstract'] ?? '',
            'doktype' => (int)$row['doktype'],
            'sys_language_uid' => (int)$row['sys_language_uid'],
            'l10n_parent' => (int)$row['l10n_parent'],
        ];
    }

    /**
     * Batch fetch all navigation pages for multiple parent UIDs in a single query
     * This significantly reduces database queries for large sites (solves N+1 problem)
     *
     * Pages with an excluded doktype are omitted and their own children are promoted
     * into their parent's position (same behavior as the single-parent findNavigationPages()).
     *
     * @param array<int> $parentUids Array of parent page UIDs
     * @param int|null $languageUid Language UID to filter by (null = all languages)
     * @param array<int, int> $excludeDoktypes Additional doktypes to exclude
     * @return array<int, array<int, array>> Pages grouped by parent UID
     */
    public function findNavigationByParentsBatch(array $parentUids, ?int $languageUid = null, array $excludeDoktypes = []): array
    {
        return $this->findNavigationByParentsBatchInternal($parentUids, $languageUid, $excludeDoktypes, []);
    }

    /**
     * @param array<int> $parentUids
     * @param array<int, int> $excludeDoktypes
     * @param array<int, bool> $visitedUids UIDs already visited to prevent infinite recursion
     * @return array<int, array<int, array>>
     */
    protected function findNavigationByParentsBatchInternal(
        array $parentUids,
        ?int $languageUid,
        array $excludeDoktypes,
        array $visitedUids,
        int $currentDepth = 0
    ): array {
        if (empty($parentUids) || $currentDepth >= self::MAX_RECURSION_DEPTH) {
            return [];
        }

        $parentUids = array_values(array_diff($parentUids, array_keys($visitedUids)));
        if (empty($parentUids)) {
            return [];
        }
        foreach ($parentUids as $parentUid) {
            $visitedUids[$parentUid] = true;
        }

        // Limit batch size to prevent memory issues
        $parentUids = array_slice($parentUids, 0, self::MAX_BATCH_SIZE);

        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('pages');
        $queryBuilder->getRestrictions()->removeAll()
            ->add(GeneralUtility::makeInstance(FrontendRestrictionContainer::class));

        $queryBuilder
            ->select(...self::NAVIGATION_FIELDS)
            ->from('pages')
            ->where(
                $queryBuilder->expr()->in('pid', $queryBuilder->createNamedParameter($parentUids, Connection::PARAM_INT_ARRAY)),
                $queryBuilder->expr()->eq('nav_hide', $queryBuilder->createNamedParameter(0, Connection::PARAM_INT)),
                $queryBuilder->expr()->eq('no_index', $queryBuilder->createNamedParameter(0, Connection::PARAM_INT))
            )
            ->orderBy('pid')
            ->addOrderBy('sorting');

        if ($languageUid !== null) {
            $queryBuilder->andWhere(
                $queryBuilder->expr()->eq('sys_language_uid', $queryBuilder->createNamedParameter($languageUid, Connection::PARAM_INT))
            );
        }

        $result = $queryBuilder->executeQuery();

        $excludedSet = array_merge(self::EXCLUDED_DOKTYPES, $excludeDoktypes);

        // Group results by parent UID, deferring excluded pages for child-promotion below
        $grouped = [];
        $excludedUidToParent = [];
        while ($row = $result->fetchAssociative()) {
            $pid = (int)$row['pid'];
            if (in_array((int)$row['doktype'], $excludedSet, true)) {
                $excludedUidToParent[(int)$row['uid']] = $pid;
                continue;
            }
            if (!isset($grouped[$pid])) {
                $grouped[$pid] = [];
            }
            $grouped[$pid][] = $this->mapRowToPageArray($row);
        }

        if (!empty($excludedUidToParent)) {
            $promotedChildren = $this->findNavigationByParentsBatchInternal(
                array_keys($excludedUidToParent),
                $languageUid,
                $excludeDoktypes,
                $visitedUids,
                $currentDepth + 1
            );
            foreach ($promotedChildren as $excludedUid => $children) {
                $originalPid = $excludedUidToParent[$excludedUid];
                if (!isset($grouped[$originalPid])) {
                    $grouped[$originalPid] = [];
                }
                foreach ($children as $child) {
                    $grouped[$originalPid][] = $child;
                }
            }
        }

        return $grouped;
    }

    /**
     * Find navigation pages with proper language fallback handling
     * Uses Core's PageRepository for overlay logic
     *
     * @param int $parentUid Parent page UID
     * @param SiteLanguage $siteLanguage The site language to fetch pages for
     * @param array<int, int> $excludeDoktypes Additional doktypes to exclude
     * @return array<int, array{uid: int, pid: int, title: string, description: string, abstract: string, doktype: int, sys_language_uid: int, l10n_parent: int}>
     */
    public function findNavigationByParentWithFallback(int $parentUid, SiteLanguage $siteLanguage, array $excludeDoktypes = []): array
    {
        // Fetch default language pages first
        $defaultLanguagePages = $this->findNavigationPages($parentUid, 0, $excludeDoktypes, []);

        if (empty($defaultLanguagePages)) {
            return [];
        }

        // If default language requested, return as-is
        if ($siteLanguage->getLanguageId() === 0) {
            return $defaultLanguagePages;
        }

        // Create Core PageRepository with proper language context for overlays
        $corePageRepository = $this->createCorePageRepository($siteLanguage);

        // Apply language overlays using Core's method
        $overlaidPages = $corePageRepository->getPagesOverlay($defaultLanguagePages);

        // Filter pages based on language visibility
        $languageAspect = LanguageAspectFactory::createFromSiteLanguage($siteLanguage);
        $result = [];

        foreach ($overlaidPages as $page) {
            if ($corePageRepository->isPageSuitableForLanguage($page, $languageAspect)) {
                $result[] = $this->mapRowToPageArray($page);
            }
        }

        return $result;
    }

    /**
     * Batch fetch pages with language fallback for multiple parents
     *
     * @param array<int> $parentUids Parent page UIDs
     * @param SiteLanguage $siteLanguage The site language
     * @param array<int, int> $excludeDoktypes Additional doktypes to exclude
     * @return array<int, array<int, array>> Pages grouped by parent UID with overlays applied
     */
    public function findNavigationByParentsBatchWithFallback(array $parentUids, SiteLanguage $siteLanguage, array $excludeDoktypes = []): array
    {
        if (empty($parentUids)) {
            return [];
        }

        // Limit batch size
        $parentUids = array_slice($parentUids, 0, self::MAX_BATCH_SIZE);

        // Fetch default language pages
        $defaultLanguagePages = $this->findNavigationByParentsBatch($parentUids, 0, $excludeDoktypes);

        if (empty($defaultLanguagePages)) {
            return [];
        }

        // If default language requested, return as-is
        if ($siteLanguage->getLanguageId() === 0) {
            return $defaultLanguagePages;
        }

        // Flatten pages for overlay processing
        $allPages = [];
        $pageToParentMap = [];
        foreach ($defaultLanguagePages as $pid => $pages) {
            foreach ($pages as $page) {
                $allPages[] = $page;
                $pageToParentMap[$page['uid']] = $pid;
            }
        }

        if (empty($allPages)) {
            return [];
        }

        // Create Core PageRepository with proper language context
        $corePageRepository = $this->createCorePageRepository($siteLanguage);

        // Apply overlays
        $overlaidPages = $corePageRepository->getPagesOverlay($allPages);

        // Filter and regroup by parent
        $languageAspect = LanguageAspectFactory::createFromSiteLanguage($siteLanguage);
        $result = [];

        foreach ($overlaidPages as $page) {
            if (!$corePageRepository->isPageSuitableForLanguage($page, $languageAspect)) {
                continue;
            }

            // Get original UID for parent mapping (before overlay)
            $originalUid = $page['_TRANSLATION_SOURCE']->uid ?? $page['uid'];
            $pid = $pageToParentMap[$originalUid] ?? (int)$page['pid'];

            if (!isset($result[$pid])) {
                $result[$pid] = [];
            }

            $result[$pid][] = $this->mapRowToPageArray($page);
        }

        return $result;
    }

    /**
     * Create a Core PageRepository instance configured for the given site language
     * This handles language overlays with proper fallback chain
     */
    protected function createCorePageRepository(SiteLanguage $siteLanguage): CorePageRepository
    {
        // Clone context to avoid modifying global state
        $context = clone $this->context;
        $context->setAspect(
            'language',
            LanguageAspectFactory::createFromSiteLanguage($siteLanguage)
        );

        return GeneralUtility::makeInstance(CorePageRepository::class, $context);
    }
}
