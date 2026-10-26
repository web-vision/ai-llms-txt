<?php

declare(strict_types=1);

namespace WebVision\AiLlmsTxt\Tests\Functional\Frontend;

use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Core\Cache\Backend\Typo3DatabaseBackend;
use TYPO3\CMS\Core\Cache\CacheManager;
use TYPO3\CMS\Core\Configuration\SiteConfiguration;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequest;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * End-to-end tests through TYPO3's frontend request handling: TypoScript registration,
 * page cache behaviour and HTTP status codes are only observable at this level.
 */
final class FrontendOutputTest extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'web-vision/ai-llms-txt',
    ];

    protected array $coreExtensionsToLoad = [
        'seo',
    ];

    // FunctionalTestCase replaces the page cache and the hash cache with NullBackend.
    // Both have to be real here, otherwise caching would be unobservable.
    protected array $configurationToUseInTestInstance = [
        'SYS' => [
            'caching' => [
                'cacheConfigurations' => [
                    'hash' => [
                        'backend' => Typo3DatabaseBackend::class,
                    ],
                    'pages' => [
                        'backend' => Typo3DatabaseBackend::class,
                    ],
                ],
            ],
        ],
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->importCSVDataSet(__DIR__ . '/../Fixtures/pages.csv');
        $this->setUpFrontendRootPage(1, ['EXT:ai_llms_txt/Tests/Functional/Fixtures/Frontend/page.typoscript']);
    }

    #[Test]
    public function llmsTxtIsServedAsPlainTextWithTheSiteStructure(): void
    {
        $this->writeSiteConfiguration([]);

        $response = $this->requestLlmsTxt();
        $body = (string)$response->getBody();

        static::assertSame(200, $response->getStatusCode());
        static::assertStringStartsWith('text/plain', $response->getHeaderLine('Content-Type'));
        static::assertSame('nosniff', $response->getHeaderLine('X-Content-Type-Options'));
        static::assertStringStartsWith('# Root Page', $body);
        static::assertStringContainsString('[Category A]', $body);
    }

    #[Test]
    public function llmsTxtIsNotPageCachedWithoutCacheLifetime(): void
    {
        $this->writeSiteConfiguration([]);
        $this->requestLlmsTxt();

        // A page below the first level: TYPO3 alone would only clear the page cache of
        // that page and its parent, never the root page llms.txt is cached under.
        $this->renamePageWithoutDataHandler(7, 'Renamed Category');

        static::assertStringContainsString('[Renamed Category]', (string)$this->requestLlmsTxt()->getBody());
    }

    #[Test]
    public function llmsTxtIsPageCachedWithCacheLifetimeAndClearedWithThePagesTag(): void
    {
        $this->writeSiteConfiguration(['llmsTxtCacheLifetime' => 900]);
        $this->requestLlmsTxt();

        $this->renamePageWithoutDataHandler(7, 'Renamed Category');
        static::assertStringContainsString(
            '[Category A]',
            (string)$this->requestLlmsTxt()->getBody(),
            'With a cache lifetime, llms.txt must be served from cache'
        );

        // This is what DataHandler does whenever any page record is saved
        $this->get(CacheManager::class)->flushCachesInGroupByTags('pages', ['pages']);

        static::assertStringContainsString('[Renamed Category]', (string)$this->requestLlmsTxt()->getBody());
    }

    #[Test]
    public function markdownVersionOfAPageIsServed(): void
    {
        $this->writeSiteConfiguration([]);

        $response = $this->requestMarkdown('/about-us');

        static::assertSame(200, $response->getStatusCode());
        static::assertStringStartsWith('text/markdown', $response->getHeaderLine('Content-Type'));
        static::assertSame('nosniff', $response->getHeaderLine('X-Content-Type-Options'));
        static::assertStringStartsWith('# SEO About Us', (string)$response->getBody());
    }

    #[Test]
    public function markdownVersionUsesTheMainHeadingOfTheContentInsteadOfThePageTitle(): void
    {
        $this->writeSiteConfiguration([]);
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/Frontend/tt_content.csv');

        $body = (string)$this->requestMarkdown('/about-us')->getBody();

        // The description follows the heading taken from the content
        static::assertMatchesRegularExpression('/^# Welcome to our company\n\n\S+ About our company\n/', $body);
        static::assertStringNotContainsString('SEO About Us', $body);
        static::assertSame(1, preg_match_all('/^# /m', $body));
        static::assertStringContainsString('We build things.', $body);
    }

    #[Test]
    public function markdownVersionStartsWithThePageTitleWhenTheContentHasNoMainHeading(): void
    {
        $this->writeSiteConfiguration([]);
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/Frontend/tt_content.csv');

        $body = (string)$this->requestMarkdown('/products')->getBody();

        static::assertMatchesRegularExpression('/^# Products\n\n\S+ Our product range\n/', $body);
        static::assertStringContainsString('## Our range', $body);
    }

    #[Test]
    public function markdownVersionOfAPageExcludedFromIndexingIsNotFound(): void
    {
        $this->writeSiteConfiguration([]);
        $this->getConnectionPool()->getConnectionForTable('pages')->update('pages', ['no_index' => 1], ['uid' => 2]);

        static::assertSame(404, $this->requestMarkdown('/about-us')->getStatusCode());
    }

    #[Test]
    public function markdownVersionIsNotFoundWhenDisabledForTheSite(): void
    {
        $this->writeSiteConfiguration(['llmsTxtEnabled' => false]);

        static::assertSame(404, $this->requestMarkdown('/about-us')->getStatusCode());
    }

    private function requestLlmsTxt(): ResponseInterface
    {
        return $this->executeFrontendSubRequest(
            (new InternalRequest('https://website.local/'))->withQueryParameters(['type' => 1699])
        );
    }

    private function requestMarkdown(string $path): ResponseInterface
    {
        return $this->executeFrontendSubRequest(
            (new InternalRequest('https://website.local' . $path))->withQueryParameters(['type' => 1701])
        );
    }

    private function renamePageWithoutDataHandler(int $uid, string $title): void
    {
        $this->getConnectionPool()->getConnectionForTable('pages')->update('pages', ['title' => $title], ['uid' => $uid]);
    }

    /**
     * @param array<string, int|bool> $additionalConfig
     */
    private function writeSiteConfiguration(array $additionalConfig): void
    {
        $siteDir = $this->instancePath . '/typo3conf/sites/test-site';
        @mkdir($siteDir, 0777, true);

        $extraLines = '';
        foreach ($additionalConfig as $key => $value) {
            $extraLines .= "\n{$key}: " . (is_bool($value) ? ($value ? 'true' : 'false') : $value);
        }

        file_put_contents(
            $siteDir . '/config.yaml',
            <<<YAML
                rootPageId: 1
                base: 'https://website.local/'
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
    }
}
