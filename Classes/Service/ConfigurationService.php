<?php

declare(strict_types=1);

namespace WebVision\AiLlmsTxt\Service;

use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Service for managing extension configuration
 */
class ConfigurationService
{
    public function __construct(
        private readonly SiteFinder $siteFinder
    ) {
    }

    protected function getCurrentSite(): ?Site
    {
        $sites = $this->siteFinder->getAllSites();
        if (empty($sites)) {
            return null;
        }

        return reset($sites);
    }

    public function getSiteUrl(): string
    {
        $site = $this->getCurrentSite();
        if ($site === null) {
            return GeneralUtility::getIndpEnv('TYPO3_REQUEST_HOST');
        }

        return (string)$site->getBase();
    }

    public function getSiteName(): string
    {
        $site = $this->getCurrentSite();
        if ($site === null) {
            return 'Website';
        }

        return $site->getIdentifier();
    }

    public function isEnabled(): bool
    {
        $site = $this->getCurrentSite();
        if ($site === null) {
            return true;
        }

        return (bool)($site->getConfiguration()['llmsTxtEnabled'] ?? true);
    }

    public function getTitleOverride(): ?string
    {
        $site = $this->getCurrentSite();
        if ($site === null) {
            return null;
        }

        $title = $site->getConfiguration()['llmsTxtTitle'] ?? '';
        return !empty($title) ? trim($title) : null;
    }

    public function getDescriptionOverride(): ?string
    {
        $site = $this->getCurrentSite();
        if ($site === null) {
            return null;
        }

        $description = $site->getConfiguration()['llmsTxtDescription'] ?? '';
        return !empty($description) ? trim($description) : null;
    }

    public function getAdditionalInfo(): ?string
    {
        $site = $this->getCurrentSite();
        if ($site === null) {
            return null;
        }

        $info = $site->getConfiguration()['llmsTxtAdditionalInfo'] ?? '';
        return !empty($info) ? trim($info) : null;
    }

    public function getContactEmail(): ?string
    {
        $site = $this->getCurrentSite();
        if ($site === null) {
            return null;
        }

        $email = $site->getConfiguration()['llmsTxtContactEmail'] ?? '';
        return !empty($email) ? trim($email) : null;
    }

    public function getKeywords(): array
    {
        $site = $this->getCurrentSite();
        if ($site === null) {
            return [];
        }

        $keywords = $site->getConfiguration()['llmsTxtKeywords'] ?? '';
        if (empty($keywords)) {
            return [];
        }

        return array_map('trim', explode(',', $keywords));
    }

    public function getMaxDepth(): int
    {
        $site = $this->getCurrentSite();
        if ($site === null) {
            return 2;
        }

        return (int)($site->getConfiguration()['llmsTxtMaxDepth'] ?? 2);
    }
}
