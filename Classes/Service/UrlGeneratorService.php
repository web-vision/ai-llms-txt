<?php

declare(strict_types=1);

namespace WebVision\AiLlmsTxt\Service;

use TYPO3\CMS\Core\Routing\RouterInterface;
use TYPO3\CMS\Core\Site\SiteFinder;

class UrlGeneratorService
{
    /**
     * Page type of the Markdown version of a page, see Configuration/TypoScript/markdown.typoscript
     */
    public const MARKDOWN_PAGE_TYPE = 1701;

    public function __construct(
        private readonly SiteFinder $siteFinder
    ) {
    }

    /**
     * Generate the absolute URL of the Markdown version of a page
     *
     * The URL is the one of page type 1701, built by the router. With the route enhancer
     * of this extension imported that is the page URL with the ".md" suffix (".../index.md"
     * for a root page), whatever suffix the regular page URL carries; without it, the
     * router falls back to "?type=1701", which works just as well.
     *
     * @param array<string, mixed> $page Page record with at least "uid", optionally "sys_language_uid"
     */
    public function generatePageUrl(array $page): string
    {
        $site = $this->siteFinder->getSiteByPageId((int)$page['uid']);
        $siteLanguage = $page['sys_language_uid'] ?? $site->getDefaultLanguage();

        return (string)$site->getRouter()->generateUri(
            (int)$page['uid'],
            ['_language' => $siteLanguage, 'type' => self::MARKDOWN_PAGE_TYPE],
            '',
            RouterInterface::ABSOLUTE_URL
        );
    }
}
