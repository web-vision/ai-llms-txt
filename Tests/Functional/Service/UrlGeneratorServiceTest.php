<?php

declare(strict_types=1);

namespace WebVision\AiLlmsTxt\Tests\Functional\Service;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;
use WebVision\AiLlmsTxt\Service\UrlGeneratorService;

/**
 * The URLs of the Markdown versions, generated with real site configurations: whether they
 * resolve depends on the route enhancers of the site, which only the router knows about.
 */
final class UrlGeneratorServiceTest extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'web-vision/ai-llms-txt',
    ];

    protected array $coreExtensionsToLoad = [
        'seo',
    ];

    private const ROUTE_ENHANCER_OF_THE_EXTENSION = <<<'YAML'
        routeEnhancers:
          PageTypeSuffix:
            type: PageType
            map:
              llms.txt: 1699
              .well-known/llms.txt: 1699
              .md: 1701
        YAML;

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/pages.csv');
    }

    /**
     * @return array<string, array{string, int, string}>
     */
    public static function siteConfigurationProvider(): array
    {
        $htmlSuffix = <<<'YAML'
            routeEnhancers:
              PageTypeSuffix:
                type: PageType
                default: '.html'
                map:
                  .html: 0
                  llms.txt: 1699
                  .well-known/llms.txt: 1699
                  .md: 1701
            YAML;
        $trailingSlash = <<<'YAML'
            routeEnhancers:
              PageTypeSuffix:
                type: PageType
                default: '/'
                map:
                  /: 0
                  llms.txt: 1699
                  .well-known/llms.txt: 1699
                  .md: 1701
            YAML;

        return [
            'route enhancer of the extension' => [self::ROUTE_ENHANCER_OF_THE_EXTENSION, 5, 'https://example.com/about-us/team.md'],
            'root page with the route enhancer of the extension' => [self::ROUTE_ENHANCER_OF_THE_EXTENSION, 1, 'https://example.com/index.md'],
            'pages ending in .html' => [$htmlSuffix, 5, 'https://example.com/about-us/team.md'],
            'root page with pages ending in .html' => [$htmlSuffix, 1, 'https://example.com/index.md'],
            'pages ending in a slash' => [$trailingSlash, 5, 'https://example.com/about-us/team.md'],
            'no route enhancer' => ['', 5, 'https://example.com/about-us/team?type=1701'],
        ];
    }

    #[Test]
    #[DataProvider('siteConfigurationProvider')]
    public function generatePageUrlReturnsAnUrlTheSiteResolvesToTheMarkdownVersion(string $routeEnhancers, int $pageId, string $expectedUrl): void
    {
        $this->writeSiteConfiguration($routeEnhancers);

        static::assertSame($expectedUrl, $this->get(UrlGeneratorService::class)->generatePageUrl(['uid' => $pageId]));
    }

    private function writeSiteConfiguration(string $routeEnhancers): void
    {
        $siteDir = $this->instancePath . '/typo3conf/sites/test-site';
        @mkdir($siteDir, 0777, true);

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
                    flag: us

                YAML . $routeEnhancers . "\n"
        );

        // TYPO3 12 has no siteConfigurationChanged(): reading the sites uncached makes
        // SiteConfiguration and SiteFinder pick up the configuration written above.
        $this->get(SiteFinder::class)->getAllSites(false);
    }
}
