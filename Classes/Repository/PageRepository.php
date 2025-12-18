<?php

declare(strict_types=1);

namespace WebVision\AiLlmsTxt\Repository;

use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\Restriction\FrontendRestrictionContainer;
use TYPO3\CMS\Core\Domain\Repository\PageRepository as CorePageRepository;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Repository for fetching page data
 * Handles all database queries related to pages using the Repository pattern
 */
class PageRepository
{
    public function __construct(
        private readonly ConnectionPool $connectionPool
    ) {}

    public function findById(int $pageId): array
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('pages');
        $queryBuilder->getRestrictions()->removeAll()
            ->add(GeneralUtility::makeInstance(FrontendRestrictionContainer::class));

        $result = $queryBuilder
            ->select('uid', 'title', 'subtitle', 'description', 'abstract')
            ->from('pages')
            ->where(
                $queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($pageId))
            )
            ->executeQuery();

        return $result->fetchAssociative() ?: [];
    }

    /**
     * Doktypes that should be excluded but their children should still be fetched
     */
    private const EXCLUDED_DOKTYPES = [
        CorePageRepository::DOKTYPE_RECYCLER,
        CorePageRepository::DOKTYPE_SYSFOLDER,
        CorePageRepository::DOKTYPE_SPACER,
        CorePageRepository::DOKTYPE_SHORTCUT,
    ];

    public function findNavigationByParent(int $parentUid): array
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('pages');

        $result = $queryBuilder
            ->select('uid', 'pid', 'title', 'description', 'abstract', 'nav_title', 'doktype')
            ->from('pages')
            ->where(
                $queryBuilder->expr()->eq('pid', $queryBuilder->createNamedParameter($parentUid)),
                $queryBuilder->expr()->eq('nav_hide', $queryBuilder->createNamedParameter(0)),
                $queryBuilder->expr()->eq('no_index', $queryBuilder->createNamedParameter(0)),
                $queryBuilder->expr()->neq('doktype', $queryBuilder->createNamedParameter(CorePageRepository::DOKTYPE_RECYCLER)),
            )
            ->orderBy('sorting')
            ->executeQuery();

        $pages = [];
        while ($row = $result->fetchAssociative()) {
            // Skip folders, spacers, and shortcuts but fetch their subpages
            if (in_array((int)$row['doktype'], self::EXCLUDED_DOKTYPES, true)) {
                // Recursively get children of skipped pages
                $childPages = $this->findNavigationByParent((int)$row['uid']);
                foreach ($childPages as $childPage) {
                    $pages[] = $childPage;
                }
                continue;
            }

            $pages[] = [
                'uid' => $row['uid'],
                'pid' => $row['pid'],
                'title' => $row['nav_title'] ?: $row['title'],
                'description' => $row['description'],
                'abstract' => $row['abstract'],
                'doktype' => $row['doktype'],
            ];
        }

        return $pages;
    }
}
