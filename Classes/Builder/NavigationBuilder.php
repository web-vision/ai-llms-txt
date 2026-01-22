<?php

declare(strict_types=1);

namespace WebVision\AiLlmsTxt\Builder;

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
                'pages' => [],
                'language' => $this->getLanguageTitle($mainPage),
            ];

            // Get subpages if depth allows
            if ($maxDepth >= 2) {
                $subPages = $this->pageRepository->findNavigationByParent($mainPage['uid']);

                foreach ($subPages as $subPage) {
                    $section['pages'][] = [
                        'uid' => $subPage['uid'],
                        'title' => $subPage['title'],
                        'url' => $this->urlGenerator->generatePageUrl($subPage),
                        'description' => $subPage['description'] ?: $subPage['abstract'] ?: '',
                        'language' => $this->getLanguageTitle($subPage),
                    ];
                }
            }

            $structure[] = $section;
        }

        return $structure;
    }

    protected function getLanguageTitle(array $page): string
    {
        $site = $this->siteFinder->getSiteByPageId($page['uid']);
        $language = $site->getLanguageById($page['sys_language_uid'] ?? 0);

        return $language ? $language->getTitle() : 'default';
    }

    /**
     * Format navigation structure as markdown lines
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

        // Sort by language key
        ksort($byLanguage);

        foreach ($byLanguage as $language => $sections) {
            $lines[] = "## {$language}";

            foreach ($sections as $section) {
            // Section header
            if (!empty($section['url'])) {
                $lines[] = "+ [{$section['title']}]({$section['url']})";
            }

            foreach ($section['pages'] as $page) {
                if (!empty($page['description'])) {
                $lines[] = "  - [{$page['title']}]({$page['url']}): {$page['description']}";
                } else {
                $lines[] = "  - [{$page['title']}]({$page['url']})";
                }
            }
            }

            $lines[] = ''; // Empty line between language sections
        }

        return $lines;
    }
}
