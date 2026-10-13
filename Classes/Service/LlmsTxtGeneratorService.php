<?php

declare(strict_types=1);

namespace WebVision\AiLlmsTxt\Service;

use TYPO3\CMS\Core\Cache\CacheManager;
use TYPO3\CMS\Core\Cache\Frontend\FrontendInterface;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Site\Entity\Site;
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
     * Cache identifier prefix
     */
    private const CACHE_PREFIX = 'llmstxt_';

    public function __construct(
        private readonly ConfigurationService $configurationService,
        private readonly PageRepository $pageRepository,
        private readonly NavigationBuilder $navigationBuilder,
        private readonly SiteFinder $siteFinder,
        private readonly Context $context
    ) {
    }

    /**
     * Generate complete llms.txt content
     * Uses caching when a cache lifetime is configured (disabled by default)
     */
    public function generateLlmsTxt(int $currentPageId): string
    {
        if (!$this->configurationService->isEnabled()) {
            return "# LLMS.TXT generation is disabled for this site\n";
        }

        $cacheLifetime = $this->configurationService->getCacheLifetime();
        if ($cacheLifetime <= 0) {
            return $this->generateContent($currentPageId);
        }

        $site = $this->siteFinder->getSiteByPageId($currentPageId);
        $cacheIdentifier = $this->calculateCacheIdentifier($site);

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
            $cache->set($cacheIdentifier, $content, ['pages'], $cacheLifetime);
        }

        return $content;
    }

    /**
     * Build the cache identifier for a site's generated llms.txt.
     *
     * The identifier MUST cover every input the generated content varies by. The page
     * tree is fetched through FrontendRestrictionContainer, which applies
     * FrontendGroupRestriction - so the navigation a visitor sees depends on their
     * frontend user groups. Keying on the site alone would let a privileged visitor's
     * output (including titles, descriptions and URLs of fe_group protected pages) be
     * served straight back to anonymous visitors on the next request.
     *
     * The result is hashed because site identifiers and group lists may contain
     * characters that FrontendInterface::PATTERN_ENTRYIDENTIFIER rejects.
     */
    public function calculateCacheIdentifier(Site $site): string
    {
        $groupIds = $this->context->getPropertyFromAspect('frontend.user', 'groupIds', [0, -1]);
        $groupIds = array_map('intval', is_array($groupIds) ? $groupIds : [$groupIds]);
        sort($groupIds);

        // The content does not vary by language today (generateContent() always uses the
        // site's default language), but keying on it keeps the cache correct if that
        // is ever fixed, instead of silently serving the wrong language from cache.
        $languageId = (int)$this->context->getPropertyFromAspect('language', 'id', 0);

        return self::CACHE_PREFIX . sha1(implode('|', [
            $site->getIdentifier(),
            (string)$site->getRootPageId(),
            (string)$languageId,
            implode(',', $groupIds),
        ]));
    }

    /**
     * Generate the llms.txt content (without caching)
     */
    protected function generateContent(int $currentPageId): string
    {
        $lines = [];

        $site = $this->siteFinder->getSiteByPageId($currentPageId);
        $homePage = $this->pageRepository->findById($site->getRootPageId());

        $title = $this->toSingleLine((string)($this->configurationService->getTitleOverride() ?: $homePage['title'] ?? ''));
        if (!empty($title)) {
            $lines[] =  "# $title";
        }

        $lines[] = '';

        $description = $this->toSingleLine((string)($this->configurationService->getDescriptionOverride() ?: $homePage['description'] ?? ''));
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

        $keywords = array_filter(array_map(
            fn (string $keyword): string => $this->toSingleLine($keyword),
            $this->configurationService->getKeywords()
        ));
        if (!empty($keywords)) {
            $lines[] = '';
            $lines[] = '**Topics:** ' . implode(', ', $keywords);
        }

        $contactEmail = $this->toSingleLine((string)$this->configurationService->getContactEmail());
        if (!empty($contactEmail)) {
            $lines[] = '**Contact:** ' . $contactEmail;
        }

        return $lines;
    }

    /**
     * Collapse a value that is rendered into a single llms.txt line onto one line.
     *
     * Page descriptions are multi-line capable text fields, and the header block writes
     * them into line-oriented constructs ("# ...", "> ...", "**Topics:** ..."). Without
     * this, an embedded newline breaks out of its construct and injects arbitrary extra
     * lines into the file - for example a fabricated navigation entry pointing at an
     * external URL, which is then handed to whatever crawler consumes llms.txt.
     *
     * Unlike NavigationBuilder::escapeMarkdown() this does not escape markdown special
     * characters: these values are headings and prose, not link labels, so escaping
     * would mangle ordinary punctuation while newlines are the actual injection vector.
     */
    protected function toSingleLine(string $value): string
    {
        return trim(preg_replace('/\s+/u', ' ', $value) ?? $value);
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
