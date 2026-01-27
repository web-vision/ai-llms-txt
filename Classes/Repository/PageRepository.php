<?php

declare(strict_types=1);

namespace WebVision\AiLlmsTxt\Repository;

use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\Database\Query\Restriction\FrontendRestrictionContainer;
use TYPO3\CMS\Core\Domain\Repository\PageRepository as CorePageRepository;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Repository for fetching page data
 * Handles all database queries related to pages using the Repository pattern
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
        private readonly ConnectionPool $connectionPool
    ) {
    }

    /**
     * Find a page by its UID
     *
     * @return array{uid: int, title: string, subtitle: string, description: string, abstract: string}|array{}
     */
    public function findById(int $pageId): array
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('pages');
        $queryBuilder->getRestrictions()->removeAll()
            ->add(GeneralUtility::makeInstance(FrontendRestrictionContainer::class));

        $result = $queryBuilder
            ->select('uid', 'title', 'subtitle', 'description', 'abstract')
            ->from('pages')
            ->where(
                $queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($pageId, Connection::PARAM_INT))
            )
            ->executeQuery();

        return $result->fetchAssociative() ?: [];
    }

    /**
     * Find navigation pages by parent UID (all languages)
     *
     * @param int $parentUid Parent page UID
     * @return array<int, array{uid: int, pid: int, title: string, description: string, abstract: string, doktype: int, sys_language_uid: int, l10n_parent: int}>
     */
    public function findNavigationByParent(int $parentUid): array
    {
        return $this->findNavigationPages($parentUid, null, []);
    }

    /**
     * Find navigation pages by parent UID filtered by language
     *
     * @param int $parentUid Parent page UID
     * @param int $languageUid Language UID to filter by
     * @return array<int, array{uid: int, pid: int, title: string, description: string, abstract: string, doktype: int, sys_language_uid: int, l10n_parent: int}>
     */
    public function findNavigationByParentAndLanguage(int $parentUid, int $languageUid = 0): array
    {
        return $this->findNavigationPages($parentUid, $languageUid, []);
    }

    /**
     * Internal method to find navigation pages with recursion protection
     *
     * @param int $parentUid Parent page UID
     * @param int|null $languageUid Language UID to filter by (null = all languages)
     * @param array<int, bool> $visitedUids UIDs already visited to prevent infinite recursion
     * @param int $currentDepth Current recursion depth
     * @return array<int, array{uid: int, pid: int, title: string, description: string, abstract: string, doktype: int, sys_language_uid: int, l10n_parent: int}>
     */
    protected function findNavigationPages(
        int $parentUid,
        ?int $languageUid,
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

        $queryBuilder = $this->createNavigationQueryBuilder($parentUid, $languageUid);
        $result = $queryBuilder->executeQuery();

        $pages = [];
        while ($row = $result->fetchAssociative()) {
            // Skip folders, spacers, and shortcuts but fetch their subpages
            if (in_array((int)$row['doktype'], self::EXCLUDED_DOKTYPES, true)) {
                // Recursively get children of skipped pages
                $childPages = $this->findNavigationPages(
                    (int)$row['uid'],
                    $languageUid ?? (int)$row['sys_language_uid'],
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
     * @param array<int> $parentUids Array of parent page UIDs
     * @param int|null $languageUid Language UID to filter by (null = all languages)
     * @return array<int, array<int, array>> Pages grouped by parent UID
     */
    public function findNavigationByParentsBatch(array $parentUids, ?int $languageUid = null): array
    {
        if (empty($parentUids)) {
            return [];
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

        // Group results by parent UID
        $grouped = [];
        while ($row = $result->fetchAssociative()) {
            $pid = (int)$row['pid'];
            if (!isset($grouped[$pid])) {
                $grouped[$pid] = [];
            }
            $grouped[$pid][] = $this->mapRowToPageArray($row);
        }

        return $grouped;
    }
}
