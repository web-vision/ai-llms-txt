<?php

declare(strict_types=1);

namespace WebVision\AiLlmsTxt\Service;

use TYPO3\CMS\Core\Site\SiteFinder;

class UrlGeneratorService
{
    public function __construct(
        private readonly SiteFinder $siteFinder
    ) {
    }

    /**
     * Generate absolute URL for a markdown page
     */
    public function generatePageUrl(array $page): string
    {
        $site = $this->siteFinder->getSiteByPageId($page['uid']);
        $siteLanguage = $page['sys_language_uid'] ?? $site->getDefaultLanguage();

        $uri = (string)$site->getRouter()->generateUri(
            $page['uid'],
            ['_language' => $siteLanguage],
            '',
            \TYPO3\CMS\Core\Routing\RouterInterface::ABSOLUTE_URL
        );

        // Remove any trailing slashes from the generated URI
        $uri = rtrim($uri, '/');

        // Prevent generating invalid URLs like 'https://example.com.md' for root pages
        if (preg_match('#^https?://[^/]+$#', $uri)) {
            $uri .= '/index';
        }

        return "{$uri}.md";
    }

}
