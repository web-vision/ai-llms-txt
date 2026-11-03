<?php

declare(strict_types=1);

namespace WebVision\AiLlmsTxt\Tests\Functional\Command;

use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;
use WebVision\AiLlmsTxt\Command\DownloadMarkdownCommand;

/**
 * The export itself fetches every page over HTTP, which a functional test cannot serve.
 * The dry run goes through the same page selection and file naming and lists the result.
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

    /**
     * SymfonyStyle wraps long lines, which can split a path
     */
    private function normalizeOutput(CommandTester $tester): string
    {
        return (string)preg_replace('/\s*\n\s*/', '', $tester->getDisplay());
    }

    private function writeSiteConfiguration(): void
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
                YAML
        );

        // TYPO3 12 has no siteConfigurationChanged(): reading the sites uncached makes
        // SiteConfiguration and SiteFinder pick up the configuration written above.
        $this->get(SiteFinder::class)->getAllSites(false);
    }
}
