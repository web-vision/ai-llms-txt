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

            // Get subpages if depth allows
            if ($maxDepth > 1) {
                // For translated pages, use l10n_parent to find children (they are stored under the default language parent)
                $parentUidForChildren = !empty($mainPage['l10n_parent']) ? (int)$mainPage['l10n_parent'] : (int)$mainPage['uid'];
                $section['children'] = $this->buildChildren(
                    $parentUidForChildren,
                    (int)$mainPage['sys_language_uid'],
                    $maxDepth - 1
                );
            }

            $structure[] = $section;
        }

        return $structure;
    }

    /**
     * Build children recursively with depth tracking
     *
     * @return array<int, array{uid: int, title: string, url: string, description: string, language: string, children: array}>
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
            $lines[] = "## {$language}";

            foreach ($sections as $section) {
                // Section header
                if (!empty($section['url'])) {
                    $lines[] = "+ [{$section['title']}]({$section['url']})";
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
            if (!empty($page['description'])) {
                $lines[] = "{$indent}- [{$page['title']}]({$page['url']}): {$page['description']}";
            } else {
                $lines[] = "{$indent}- [{$page['title']}]({$page['url']})";
            }

            // Recursively format nested children
            if (!empty($page['children'])) {
                $this->formatChildrenAsMarkdown($page['children'], $lines, $depth + 1);
            }
        }
    }
}
