<?php

declare(strict_types=1);

namespace WebVision\AiLlmsTxt\Tests\Functional\Service;

use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Cache\CacheManager;
use TYPO3\CMS\Core\Configuration\SiteConfiguration;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Context\UserAspect;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;
use WebVision\AiLlmsTxt\Service\ConfigurationService;
use WebVision\AiLlmsTxt\Service\LlmsTxtGeneratorService;

/**
 * Functional tests for LlmsTxtGeneratorService
 *
 * Tests the service can be instantiated.
 * Note: Full integration tests with site configuration require SiteBasedTestTrait
 * which involves more complex setup. These tests focus on the class structure.
 */
final class LlmsTxtGeneratorServiceTest extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'web-vision/ai-llms-txt',
    ];

    protected array $coreExtensionsToLoad = [
        'seo',
    ];

    // FunctionalTestCase forces the "hash" cache to NullBackend by default (to save
    // setup time, since most functional tests don't need real caching). This test
    // class specifically verifies caching behavior, so it restores the real backend.
    protected array $configurationToUseInTestInstance = [
        'SYS' => [
            'caching' => [
                'cacheConfigurations' => [
                    'hash' => [
                        'backend' => \TYPO3\CMS\Core\Cache\Backend\Typo3DatabaseBackend::class,
                    ],
                ],
            ],
        ],
    ];

    private LlmsTxtGeneratorService $subject;

    protected function setUp(): void
    {
        parent::setUp();

        $this->importCSVDataSet(__DIR__ . '/../Fixtures/pages.csv');

        $this->subject = $this->get(LlmsTxtGeneratorService::class);
    }

    #[Test]
    public function llmsTxtGeneratorServiceCanBeInstantiated(): void
    {
        static::assertInstanceOf(LlmsTxtGeneratorService::class, $this->subject);
    }

    #[Test]
    public function classHasGenerateLlmsTxtMethod(): void
    {
        static::assertTrue(method_exists($this->subject, 'generateLlmsTxt'));
    }

    #[Test]
    public function cacheLifetimeZeroSkipsCacheReadAndWrite(): void
    {
        $site = $this->writeSiteConfiguration(['llmsTxtCacheLifetime' => 0]);
        $this->setRequestForSite($site);

        $cache = GeneralUtility::makeInstance(CacheManager::class)->getCache('hash');
        $cacheIdentifier = $this->subject->calculateCacheIdentifier($site);
        $cache->set($cacheIdentifier, 'SENTINEL CACHED CONTENT', ['pages'], 3600);

        $result = $this->subject->generateLlmsTxt(1);

        static::assertNotSame('SENTINEL CACHED CONTENT', $result, 'Disabled caching must not read from an existing cache entry');
        static::assertSame(
            'SENTINEL CACHED CONTENT',
            $cache->get($cacheIdentifier),
            'Disabled caching must not overwrite an existing cache entry either'
        );
    }

    #[Test]
    public function unconfiguredCacheLifetimeDefaultsToDisabled(): void
    {
        $site = $this->writeSiteConfiguration([]);
        $this->setRequestForSite($site);

        $cache = GeneralUtility::makeInstance(CacheManager::class)->getCache('hash');
        $cacheIdentifier = $this->subject->calculateCacheIdentifier($site);

        $this->subject->generateLlmsTxt(1);

        static::assertFalse($cache->has($cacheIdentifier), 'Unconfigured cache lifetime must default to disabled, not the previous hardcoded 1h cache');
    }

    #[Test]
    public function configuredCacheLifetimeStoresAndServesCachedContent(): void
    {
        $site = $this->writeSiteConfiguration(['llmsTxtCacheLifetime' => 900]);
        $this->setRequestForSite($site);

        $cache = GeneralUtility::makeInstance(CacheManager::class)->getCache('hash');
        $cacheIdentifier = $this->subject->calculateCacheIdentifier($site);
        static::assertFalse($cache->has($cacheIdentifier));

        $this->subject->generateLlmsTxt(1);

        static::assertTrue($cache->has($cacheIdentifier), 'Configured lifetime should populate the cache');

        // Overwrite the cache entry directly; a second call must serve this value
        // rather than regenerating, proving it actually reads from cache.
        $cache->set($cacheIdentifier, 'SENTINEL FROM CACHE', ['pages'], 900);
        $result = $this->subject->generateLlmsTxt(1);

        static::assertSame('SENTINEL FROM CACHE', $result);
    }

    #[Test]
    public function cacheIdentifierIsScopedToFrontendUserGroups(): void
    {
        $site = $this->writeSiteConfiguration(['llmsTxtCacheLifetime' => 900]);
        $context = $this->get(Context::class);

        $context->setAspect('frontend.user', new UserAspect(null, [0, -1]));
        $anonymousIdentifier = $this->subject->calculateCacheIdentifier($site);

        $context->setAspect('frontend.user', new UserAspect(null, [0, -1, 2]));
        $memberIdentifier = $this->subject->calculateCacheIdentifier($site);

        static::assertNotSame(
            $anonymousIdentifier,
            $memberIdentifier,
            'Navigation is filtered by fe_group, so a member\'s cache entry must not be reusable for anonymous visitors'
        );
    }

    #[Test]
    public function cacheIdentifierIsStableForTheSameGroupsRegardlessOfOrder(): void
    {
        $site = $this->writeSiteConfiguration(['llmsTxtCacheLifetime' => 900]);
        $context = $this->get(Context::class);

        $context->setAspect('frontend.user', new UserAspect(null, [0, -1, 5, 2]));
        $first = $this->subject->calculateCacheIdentifier($site);

        $context->setAspect('frontend.user', new UserAspect(null, [2, 5, -1, 0]));
        $second = $this->subject->calculateCacheIdentifier($site);

        static::assertSame($first, $second, 'Group order must not fragment the cache');
    }

    #[Test]
    public function cacheIdentifierIsAValidCacheEntryIdentifier(): void
    {
        // Site identifiers may contain characters (dots, for instance) that
        // FrontendInterface::PATTERN_ENTRYIDENTIFIER rejects.
        $site = $this->writeSiteConfiguration(['llmsTxtCacheLifetime' => 900]);
        $cache = GeneralUtility::makeInstance(CacheManager::class)->getCache('hash');

        static::assertTrue($cache->isValidEntryIdentifier($this->subject->calculateCacheIdentifier($site)));
    }

    private function writeSiteConfiguration(array $additionalConfig): Site
    {
        $siteDir = $this->instancePath . '/typo3conf/sites/test-site';
        @mkdir($siteDir, 0777, true);

        $extraLines = '';
        foreach ($additionalConfig as $key => $value) {
            $extraLines .= "\n{$key}: " . (is_int($value) ? $value : "'{$value}'");
        }

        file_put_contents(
            $siteDir . '/config.yaml',
            <<<YAML
                rootPageId: 1
                base: 'https://example.com/'
                languages:
                  -
                    languageId: 0
                    title: English
                    navigationTitle: English
                    base: '/'
                    locale: en_US.UTF-8
                    flag: us{$extraLines}
                YAML
        );

        $this->get(SiteConfiguration::class)->siteConfigurationChanged();
        $this->get(SiteFinder::class)->siteConfigurationChanged();

        return $this->get(SiteFinder::class)->getSiteByIdentifier('test-site');
    }

    private function setRequestForSite(Site $site): void
    {
        $request = (new ServerRequest())->withAttribute('site', $site);
        $this->get(ConfigurationService::class)->setRequest($request);
    }
}
