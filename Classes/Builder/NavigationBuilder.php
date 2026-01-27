<?php

declare(strict_types=1);

namespace WebVision\AiLlmsTxt\Builder;

use TYPO3\CMS\Core\Exception\SiteNotFoundException;
use TYPO3\CMS\Core\Site\SiteFinder;
use WebVision\AiLlmsTxt\Repository\PageRepository;
use WebVision\AiLlmsTxt\Service\UrlGeneratorService;

/**
 * Builder for creating hierarchical navigation structures
 * Uses the Builder pattern to construct complex navigation data
 */
class NavigationBuilder
{
    public function __construct(
        private readonly SiteFinder $siteFinder,
        private readonly PageRepository $pageRepository,
        private readonly UrlGeneratorService $urlGenerator
    ) {
    }

    /**
     * Build hierarchical navigation structure
     * Uses batch queries to minimize database calls for large sites
     *
     * @param int $rootPageUid The root page UID to start building from
     * @param int $maxDepth Maximum depth of navigation (1 = only main pages, 2+ = include children)
     * @return array<int, array{title: string, description: string, url: string, children: array, language: string}>
     */
    public function build(int $rootPageUid, int $maxDepth = 2): array
    {
        $structure = [];

        // Get main navigation pages (level 1)
        $mainPages = $this->pageRepository->findNavigationByParent($rootPageUid);

        foreach ($mainPages as $mainPage) {
            $section = [
                'title' => $mainPage['title'],
                'description' => $mainPage['description'] ?: $mainPage['abstract'] ?: '',
                'url' => $this->urlGenerator->generatePageUrl($mainPage),
                'children' => [],
                'language' => $this->getLanguageTitle($mainPage),
            ];

            // Get subpages if depth allows - use optimized batch method
            if ($maxDepth > 1) {
                $parentUidForChildren = !empty($mainPage['l10n_parent']) ? (int)$mainPage['l10n_parent'] : (int)$mainPage['uid'];
                $section['children'] = $this->buildChildrenOptimized(
                    [$parentUidForChildren],
                    (int)$mainPage['sys_language_uid'],
                    $maxDepth - 1
                );
            }

            $structure[] = $section;
        }

        return $structure;
    }

    /**
     * Build children using batch queries to reduce N+1 problem
     * Fetches all children for given parent UIDs in a single query per depth level
     *
     * @param array<int> $parentUids Array of parent UIDs to fetch children for
     * @param int $languageUid Language UID to filter by
     * @param int $remainingDepth Remaining depth levels to fetch
     * @return array<int, array{uid: int, title: string, url: string, description: string, language: string, children: array}>
     */
    protected function buildChildrenOptimized(array $parentUids, int $languageUid, int $remainingDepth): array
    {
        if (empty($parentUids) || $remainingDepth < 1) {
            return [];
        }

        // Batch fetch all children for all parent UIDs in one query
        $batchedPages = $this->pageRepository->findNavigationByParentsBatch($parentUids, $languageUid);

        $children = [];
        $nextLevelParentUids = [];

        foreach ($parentUids as $parentUid) {
            $subPages = $batchedPages[$parentUid] ?? [];

            foreach ($subPages as $subPage) {
                $childKey = count($children);
                $children[$childKey] = [
                    'uid' => $subPage['uid'],
                    'parentUid' => $parentUid,
                    'title' => $subPage['title'],
                    'url' => $this->urlGenerator->generatePageUrl($subPage),
                    'description' => $subPage['description'] ?: $subPage['abstract'] ?: '',
                    'language' => $this->getLanguageTitle($subPage),
                    'children' => [],
                ];

                // Collect parent UIDs for next level
                if ($remainingDepth > 1) {
                    $childParentUid = !empty($subPage['l10n_parent']) ? (int)$subPage['l10n_parent'] : (int)$subPage['uid'];
                    $nextLevelParentUids[$childParentUid] = $childKey;
                }
            }
        }

        // Recursively fetch next level with batch query
        if ($remainingDepth > 1 && !empty($nextLevelParentUids)) {
            $nextLevelChildren = $this->buildChildrenOptimized(
                array_keys($nextLevelParentUids),
                $languageUid,
                $remainingDepth - 1
            );

            // Assign children to their parents
            foreach ($nextLevelChildren as $child) {
                $parentKey = $nextLevelParentUids[$child['parentUid']] ?? null;
                if ($parentKey !== null && isset($children[$parentKey])) {
                    unset($child['parentUid']);
                    $children[$parentKey]['children'][] = $child;
                }
            }
        }

        // Clean up parentUid from final result
        return array_map(function ($child) {
            unset($child['parentUid']);
            return $child;
        }, array_values($children));
    }

    /**
     * Build children recursively with depth tracking (legacy method, kept for compatibility)
     *
     * @return array<int, array{uid: int, title: string, url: string, description: string, language: string, children: array}>
     * @deprecated Use buildChildrenOptimized for better performance on large sites
     */
    protected function buildChildren(int $parentUid, int $languageUid, int $remainingDepth): array
    {
        $children = [];
        $subPages = $this->pageRepository->findNavigationByParentAndLanguage($parentUid, $languageUid);

        foreach ($subPages as $subPage) {
            $child = [
                'uid' => $subPage['uid'],
                'title' => $subPage['title'],
                'url' => $this->urlGenerator->generatePageUrl($subPage),
                'description' => $subPage['description'] ?: $subPage['abstract'] ?: '',
                'language' => $this->getLanguageTitle($subPage),
                'children' => [],
            ];

            // Recursively fetch deeper levels if depth allows
            if ($remainingDepth > 1) {
                $childParentUid = !empty($subPage['l10n_parent']) ? (int)$subPage['l10n_parent'] : (int)$subPage['uid'];
                $child['children'] = $this->buildChildren(
                    $childParentUid,
                    (int)$subPage['sys_language_uid'],
                    $remainingDepth - 1
                );
            }

            $children[] = $child;
        }

        return $children;
    }

    protected function getLanguageTitle(array $page): string
    {
        try {
            $site = $this->siteFinder->getSiteByPageId($page['uid']);
            $languageId = (int)($page['sys_language_uid'] ?? 0);
            $language = $site->getLanguageById($languageId);

            return $language->getTitle();
        } catch (SiteNotFoundException|\InvalidArgumentException) {
            return 'default';
        }
    }

    /**
     * Format navigation structure as markdown lines
     *
     * @param array $navigationStructure The navigation structure from build()
     * @return array<int, string> Lines of markdown content
     */
    public function formatAsMarkdown(array $navigationStructure): array
    {
        $lines = [];
        // Group by language
        $byLanguage = [];
        foreach ($navigationStructure as $section) {
            $lang = $section['language'] ?? 'default';
            if (!isset($byLanguage[$lang])) {
                $byLanguage[$lang] = [];
            }
            $byLanguage[$lang][] = $section;
        }

        foreach ($byLanguage as $language => $sections) {
            $escapedLanguage = $this->escapeMarkdown($language);
            $lines[] = "## {$escapedLanguage}";

            foreach ($sections as $section) {
                // Section header with proper escaping
                if (!empty($section['url'])) {
                    $title = $this->escapeMarkdown($section['title'] ?? '');
                    $url = $this->escapeUrl($section['url'] ?? '');
                    $lines[] = "+ [{$title}]({$url})";
                }

                $this->formatChildrenAsMarkdown($section['children'] ?? [], $lines, 1);
            }

            $lines[] = ''; // Empty line between language sections
        }

        return $lines;
    }

    /**
     * Recursively format children as markdown
     * @param array $children Children to format
     * @param array<int, string> $lines Reference to lines array
     * @param int $depth Current depth for indentation
     */
    protected function formatChildrenAsMarkdown(array $children, array &$lines, int $depth): void
    {
        $indent = str_repeat('  ', $depth);

        foreach ($children as $page) {
            $title = $this->escapeMarkdown($page['title'] ?? '');
            $url = $this->escapeUrl($page['url'] ?? '');
            $description = $this->escapeMarkdown($page['description'] ?? '');

            if (!empty($description)) {
                $lines[] = "{$indent}- [{$title}]({$url}): {$description}";
            } else {
                $lines[] = "{$indent}- [{$title}]({$url})";
            }

            // Recursively format nested children
            if (!empty($page['children'])) {
                $this->formatChildrenAsMarkdown($page['children'], $lines, $depth + 1);
            }
        }
    }

    /**
     * Escape special markdown characters in text
     */
    protected function escapeMarkdown(string $text): string
    {
        // Escape markdown special characters: [ ] ( ) \ ` * _ { } # + - . !
        // The replacement pattern needs 4 backslashes to produce a literal backslash + captured char
        return preg_replace('/([\[\]()\\\`*_{}#+\-.!])/', '\\\\$1', $text) ?? $text;
    }

    /**
     * Escape URL for safe use in markdown links
     */
    protected function escapeUrl(string $url): string
    {
        // Escape parentheses in URLs which break markdown link syntax
        return str_replace(['(', ')'], ['%28', '%29'], $url);
    }
}
