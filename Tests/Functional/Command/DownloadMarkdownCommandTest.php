<?php

declare(strict_types=1);

namespace WebVision\AiLlmsTxt\Tests\Functional\Command;

use GuzzleHttp\Promise\Create;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\RequestInterface;
use Psr\Log\NullLogger;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use TYPO3\CMS\Core\Configuration\SiteConfiguration;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;
use WebVision\AiLlmsTxt\Command\DownloadMarkdownCommand;
use WebVision\AiLlmsTxt\Repository\PageRepository;
use WebVision\AiLlmsTxt\Service\ConfigurationService;
use WebVision\AiLlmsTxt\Service\MarkdownConverterService;
use WebVision\AiLlmsTxt\Service\UrlGeneratorService;

/**
 * The export itself fetches every page over HTTP, which a functional test cannot serve.
 * The dry run goes through the same page selection and file naming and lists the result;
 * tests of the export answer its requests with a handler of their own.
 */
final class DownloadMarkdownCommandTest extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'web-vision/ai-llms-txt',
    ];

    protected array $coreExtensionsToLoad = [
        'seo',
    ];

    private string $outputDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/pages.csv');
        $this->writeSiteConfiguration();
        $this->outputDir = $this->instancePath . '/markdown-export';
    }

    #[Test]
    public function dryRunListsTheWholePageTreeNamedAfterTheSlugs(): void
    {
        $tester = $this->runCommand([]);
        $output = $this->normalizeOutput($tester);

        static::assertSame(Command::SUCCESS, $tester->getStatusCode());
        $siteDir = $this->outputDir . '/test-site/';
        // The root page, all levels below it, and the page of a folder in its place
        static::assertStringContainsString($siteDir . 'index.md', $output);
        static::assertStringContainsString($siteDir . 'about-us.md', $output);
        static::assertStringContainsString($siteDir . 'about-us-team.md', $output);
        static::assertStringContainsString($siteDir . 'products-category-a-sub.md', $output);
        static::assertStringContainsString($siteDir . 'folder-page.md', $output);
        static::assertStringContainsString($siteDir . 'contact-nested-folder-deep.md', $output);
        // Hidden in menus, deleted, or a folder itself
        static::assertStringNotContainsString('hidden.md', $output);
        static::assertStringNotContainsString('deleted.md', $output);
        static::assertStringNotContainsString($siteDir . 'folder.md', $output);
    }

    #[Test]
    public function dryRunLeavesOutThePageTypesExcludedForTheSite(): void
    {
        $siteDir = $this->outputDir . '/test-site/';
        static::assertStringContainsString($siteDir . 'products-internal-notes.md', $this->normalizeOutput($this->runCommand([])));

        $this->writeSiteConfiguration("llmsTxtExcludeDoktypes: '6'\n");
        $output = $this->normalizeOutput($this->runCommand([]));

        static::assertStringNotContainsString($siteDir . 'products-internal-notes.md', $output);
        // As in llms.txt, the subpage of the excluded page takes its place
        static::assertStringContainsString($siteDir . 'products-internal-notes-nested.md', $output);
    }

    #[Test]
    public function dryRunCreatesNoDirectory(): void
    {
        $this->runCommand([]);

        static::assertDirectoryDoesNotExist($this->outputDir);
    }

    #[Test]
    public function dryRunNamesFilesAfterUidOrTitleOnRequest(): void
    {
        static::assertStringContainsString('/test-site/page-5.md', $this->normalizeOutput($this->runCommand(['--format' => 'uid'])));
        static::assertStringContainsString('/test-site/team.md', $this->normalizeOutput($this->runCommand(['--format' => 'title'])));
    }

    #[Test]
    public function exportSkipsPagesThatRedirectWithoutFetchingTheTarget(): void
    {
        $requestedUrls = [];
        $httpHandler = static function (RequestInterface $request) use (&$requestedUrls): PromiseInterface {
            $requestedUrls[] = (string)$request->getUri();
            if ($request->getUri()->getHost() !== 'example.com') {
                return Create::promiseFor(new Response(200, [], 'Response of an address only the server can reach'));
            }
            // "About Us" stands in for an External URL page, which TYPO3 redirects to its URL
            if ($request->getUri()->getPath() === '/about-us') {
                return Create::promiseFor(new Response(303, ['Location' => 'http://169.254.169.254/latest/meta-data/']));
            }
            return Create::promiseFor(new Response(200, [], "# Page\n\nContent"));
        };

        $tester = $this->runExport($httpHandler);

        static::assertSame(Command::SUCCESS, $tester->getStatusCode());
        static::assertSame([], array_values(array_filter(
            $requestedUrls,
            static fn (string $url): bool => !str_starts_with($url, 'https://example.com/')
        )));
        $siteDir = $this->outputDir . '/test-site/';
        static::assertFileDoesNotExist($siteDir . 'about-us.md');
        static::assertFileExists($siteDir . 'about-us-team.md');
        static::assertStringContainsString('Processed 12 pages successfully, 1 skipped, 0 errors', $this->normalizeOutput($tester));
    }

    #[Test]
    public function unknownSiteFails(): void
    {
        $tester = new CommandTester($this->get(DownloadMarkdownCommand::class));
        $tester->execute(['siteIdentifier' => 'does-not-exist', '--dry-run' => true]);

        static::assertSame(Command::FAILURE, $tester->getStatusCode());
        static::assertStringContainsString('does not exist', $this->normalizeOutput($tester));
    }

    /**
     * @param array<string, string|bool> $options
     */
    private function runCommand(array $options): CommandTester
    {
        $tester = new CommandTester($this->get(DownloadMarkdownCommand::class));
        $tester->execute(array_merge([
            'siteIdentifier' => 'test-site',
            '--output-dir' => $this->outputDir,
            '--dry-run' => true,
        ], $options));

        return $tester;
    }

    private function runExport(callable $httpHandler): CommandTester
    {
        $command = new DownloadMarkdownCommand(
            $this->get(SiteFinder::class),
            $this->get(PageRepository::class),
            $this->get(MarkdownConverterService::class),
            $this->get(UrlGeneratorService::class),
            $this->get(ConfigurationService::class),
            new NullLogger(),
            $httpHandler
        );
        $tester = new CommandTester($command);
        $tester->execute([
            'siteIdentifier' => 'test-site',
            '--output-dir' => $this->outputDir,
        ]);

        return $tester;
    }

    /**
     * SymfonyStyle wraps long lines, which can split a path
     */
    private function normalizeOutput(CommandTester $tester): string
    {
        return (string)preg_replace('/\s*\n\s*/', '', $tester->getDisplay());
    }

    private function writeSiteConfiguration(string $additionalConfiguration = ''): void
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

                YAML . $additionalConfiguration
        );

        $this->get(SiteConfiguration::class)->siteConfigurationChanged();
        $this->get(SiteFinder::class)->siteConfigurationChanged();
    }
}
