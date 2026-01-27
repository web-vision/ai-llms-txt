<?php

declare(strict_types=1);

namespace WebVision\AiLlmsTxt\Service;

use TYPO3\CMS\Core\Cache\CacheManager;
use TYPO3\CMS\Core\Cache\Frontend\FrontendInterface;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use WebVision\AiLlmsTxt\Builder\NavigationBuilder;
use WebVision\AiLlmsTxt\Repository\PageRepository;

/**
 * Application Service for generating llms.txt content
 * Orchestrates repositories, builders, and other services to build the final output
 * Implements caching for better performance on large sites
 */
class LlmsTxtGeneratorService
{
    /**
     * Cache lifetime in seconds (1 hour default)
     */
    private const CACHE_LIFETIME = 3600;

    /**
     * Cache identifier prefix
     */
    private const CACHE_PREFIX = 'llmstxt_';

    public function __construct(
        private readonly ConfigurationService $configurationService,
        private readonly PageRepository $pageRepository,
        private readonly NavigationBuilder $navigationBuilder,
        private readonly SiteFinder $siteFinder
    ) {
    }

    /**
     * Generate complete llms.txt content
     * Uses caching when available to improve performance
     */
    public function generateLlmsTxt(int $currentPageId): string
    {
        if (!$this->configurationService->isEnabled()) {
            return "# LLMS.TXT generation is disabled for this site\n";
        }

        $site = $this->siteFinder->getSiteByPageId($currentPageId);
        $cacheIdentifier = self::CACHE_PREFIX . $site->getIdentifier() . '_' . $site->getRootPageId();

        // Try to get from cache first
        $cache = $this->getCache();
        if ($cache !== null && $cache->has($cacheIdentifier)) {
            $cached = $cache->get($cacheIdentifier);
            if (is_string($cached)) {
                return $cached;
            }
        }

        // Generate fresh content
        $content = $this->generateContent($currentPageId);

        // Store in cache if available
        if ($cache !== null) {
            $cache->set($cacheIdentifier, $content, ['pages'], self::CACHE_LIFETIME);
        }

        return $content;
    }

    /**
     * Generate the llms.txt content (without caching)
     */
    protected function generateContent(int $currentPageId): string
    {
        $lines = [];

        $site = $this->siteFinder->getSiteByPageId($currentPageId);
        $homePage = $this->pageRepository->findById($site->getRootPageId());

        $title = $this->configurationService->getTitleOverride() ?: $homePage['title'] ?? '';
        if (!empty($title)) {
            $lines[] =  "# $title";
        }

        $lines[] = '';

        $description = $this->configurationService->getDescriptionOverride() ?: $homePage['description'] ?? '';
        if (!empty($description)) {
            $lines[] = "> $description";
        }

        $lines = array_merge($lines, $this->buildMetadataSection());

        $lines[] = '';
        $lines[] = 'Main Page Structure';
        $lines[] = '';

        $maxDepth = $this->configurationService->getMaxDepth();
        $siteLanguage = $site->getDefaultLanguage();
        $navigationStructure = $this->navigationBuilder->build(
            $site->getRootPageId(),
            $maxDepth,
            $siteLanguage
        );
        $navigationLines = $this->navigationBuilder->formatAsMarkdown($navigationStructure);
        $lines = array_merge($lines, $navigationLines);

        $additionalInfo = $this->configurationService->getAdditionalInfo();
        if (!empty($additionalInfo)) {
            $lines[] = '';
            $lines[] = '---';
            $lines[] = '';
            $lines[] = $additionalInfo;
        }

        return implode("\n", $lines) . "\n";
    }

    /**
     * Build metadata section (topics, contact)
     */
    protected function buildMetadataSection(): array
    {
        $lines = [];

        $keywords = $this->configurationService->getKeywords();
        if (!empty($keywords)) {
            $lines[] = '';
            $lines[] = '**Topics:** ' . implode(', ', $keywords);
        }

        $contactEmail = $this->configurationService->getContactEmail();
        if (!empty($contactEmail)) {
            $lines[] = '**Contact:** ' . $contactEmail;
        }

        return $lines;
    }

    /**
     * Get the TYPO3 cache frontend, returns null if not available
     */
    protected function getCache(): ?FrontendInterface
    {
        try {
            $cacheManager = GeneralUtility::makeInstance(CacheManager::class);
            return $cacheManager->getCache('hash');
        } catch (\Exception) {
            // Cache not available, gracefully degrade
            return null;
        }
    }
}
