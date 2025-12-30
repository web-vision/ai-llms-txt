<?php

declare(strict_types=1);

namespace WebVision\AiLlmsTxt\Builder;

use WebVision\AiLlmsTxt\Repository\PageRepository;
use WebVision\AiLlmsTxt\Service\UrlGeneratorService;

/**
 * Builder for creating hierarchical navigation structures
 * Uses the Builder pattern to construct complex navigation data
 */
class NavigationBuilder
{
    public function __construct(
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
                'url' => $this->urlGenerator->generatePageUrl($mainPage['uid']),
                'pages' => [],
            ];

            // Get subpages if depth allows
            if ($maxDepth >= 2) {
                $subPages = $this->pageRepository->findNavigationByParent($mainPage['uid']);

                foreach ($subPages as $subPage) {
                    $section['pages'][] = [
                        'uid' => $subPage['uid'],
                        'title' => $subPage['title'],
                        'url' => $this->urlGenerator->generatePageUrl($subPage['uid']),
                        'description' => $subPage['description'] ?: $subPage['abstract'] ?: '',
                    ];
                }
            }

            $structure[] = $section;
        }

        return $structure;
    }

    /**
     * Format navigation structure as markdown lines
     */
    public function formatAsMarkdown(array $navigationStructure): array
    {
        $lines = [];

        foreach ($navigationStructure as $section) {
            // Section header (plain text)
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

            $lines[] = ''; // Empty line between sections
        }

        return $lines;
    }
}
