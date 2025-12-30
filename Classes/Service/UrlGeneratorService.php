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
    public function generatePageUrl(int $pageUid): string
    {
        $site = $this->siteFinder->getSiteByPageId($pageUid);
        //@todo: What about language handling? For now we ignore it. As LLMs dont seem to care about it.
        $url = (string)$site->getRouter()->generateUri($pageUid);
        return $url . '.md';
    }
}
