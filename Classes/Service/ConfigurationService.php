<?php

declare(strict_types=1);

namespace WebVision\AiLlmsTxt\Service;

use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Information\Typo3Version;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class ConfigurationService
{
    /**
     * Request pointer, if injected. Use getRequest() instead of reading this property directly.
     */
    private ?ServerRequestInterface $request = null;

    public function __construct(
        private readonly SiteFinder $siteFinder
    ) {
    }

    public function setRequest(ServerRequestInterface $request): void
    {
        $this->request = $request;
    }

    protected function getRequest(): ServerRequestInterface
    {
        if ($this->request !== null) {
            return $this->request;
        }

        // Fallback to global request for backward compatibility
        if (($GLOBALS['TYPO3_REQUEST'] ?? null) instanceof ServerRequestInterface) {
            return $GLOBALS['TYPO3_REQUEST'];
        }

        throw new \RuntimeException(
            'No request available. Call setRequest() before using ConfigurationService methods.',
            1765368301
        );
    }

    protected function getCurrentSite(): ?Site
    {
        $request = $this->getRequest();
        $site = $request->getAttribute('site');

        if (!$site instanceof Site) {
            $site = $this->siteFinder->getSiteByPageId(
                $this->getCurrentPageId()
            );
        }

        return $site;
    }

    public function getCurrentPageId(): int
    {
        $request = $this->getRequest();
        $version = (string)GeneralUtility::makeInstance(Typo3Version::class)->getMajorVersion();

        if (version_compare($version, '14', '<')) {
            // @extensionScannerIgnoreLine
            if (isset($GLOBALS['TSFE']) && isset($GLOBALS['TSFE']->id)) {
                // @extensionScannerIgnoreLine
                return (int)$GLOBALS['TSFE']->id;
            }
            throw new \RuntimeException('Could not determine current page ID in TYPO3 v12 context.', 1765368300);
        }

        return (int)$request->getAttribute('frontend.page.information')->getId();
    }

    public function getSiteUrl(): string
    {
        $site = $this->getCurrentSite();
        return (string)$site->getBase();
    }

    public function getSiteName(): string
    {
        $site = $this->getCurrentSite();
        return $site->getIdentifier();
    }

    public function isEnabled(): bool
    {
        $site = $this->getCurrentSite();
        return (bool)($site->getConfiguration()['llmsTxtEnabled'] ?? true);
    }

    public function getTitleOverride(): ?string
    {
        $site = $this->getCurrentSite();
        $title = $site->getConfiguration()['llmsTxtTitle'] ?? '';
        return !empty($title) ? trim($title) : null;
    }

    public function getDescriptionOverride(): ?string
    {
        $site = $this->getCurrentSite();
        $description = $site->getConfiguration()['llmsTxtDescription'] ?? '';
        return !empty($description) ? trim($description) : null;
    }

    public function getAdditionalInfo(): ?string
    {
        $site = $this->getCurrentSite();
        $info = $site->getConfiguration()['llmsTxtAdditionalInfo'] ?? '';
        return !empty($info) ? trim($info) : null;
    }

    public function getContactEmail(): ?string
    {
        $site = $this->getCurrentSite();
        $email = $site->getConfiguration()['llmsTxtContactEmail'] ?? '';
        return !empty($email) ? trim($email) : null;
    }

    public function getKeywords(): array
    {
        $site = $this->getCurrentSite();

        $keywords = $site->getConfiguration()['llmsTxtKeywords'] ?? '';
        if (empty($keywords)) {
            return [];
        }

        return array_map('trim', explode(',', $keywords));
    }

    public function getMaxDepth(): int
    {
        $site = $this->getCurrentSite();

        return (int)($site->getConfiguration()['llmsTxtMaxDepth'] ?? 2);
    }
}
