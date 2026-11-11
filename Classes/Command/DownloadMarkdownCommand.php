<?php

declare(strict_types=1);

namespace WebVision\AiLlmsTxt\Command;

use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Yaml\Yaml;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Exception\SiteNotFoundException;
use TYPO3\CMS\Core\Resource\Exception\InvalidPathException;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Utility\PathUtility;
use WebVision\AiLlmsTxt\Repository\PageRepository;
use WebVision\AiLlmsTxt\Service\ConfigurationService;
use WebVision\AiLlmsTxt\Service\MarkdownConverterService;
use WebVision\AiLlmsTxt\Service\UrlGeneratorService;

/**
 * Command to download TYPO3 pages as Markdown files
 */
final class DownloadMarkdownCommand extends Command
{
    private const DEFAULT_OUTPUT_DIR = 'var/markdown-export';

    /**
     * Number of parent pages whose subpages are fetched with one query
     */
    private const PARENTS_PER_QUERY = 500;
    private readonly Client $httpClient;

    /**
     * @param callable|null $httpHandler Sends the requests of the export, Guzzle's default
     *   handler if null. Tests pass one that answers without a network.
     */
    public function __construct(
        private readonly SiteFinder $siteFinder,
        private readonly PageRepository $pageRepository,
        private readonly MarkdownConverterService $markdownConverter,
        private readonly UrlGeneratorService $urlGenerator,
        private readonly ConfigurationService $configurationService,
        private readonly LoggerInterface $logger,
        ?callable $httpHandler = null
    ) {
        parent::__construct();
        $this->httpClient = new Client([
            'handler' => HandlerStack::create($httpHandler),
            'timeout' => 30,
            'http_errors' => false,
            // An "External URL" page or a redirect record sends the request wherever an editor
            // wants, also to addresses that only this server can reach. Its target is never
            // fetched; the page is skipped.
            'allow_redirects' => false,
        ]);
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Download TYPO3 pages as Markdown files')
            ->addArgument(
                'siteIdentifier',
                InputArgument::OPTIONAL,
                'Site identifier to process (processes all sites if not specified)'
            )
            ->addOption(
                'output-dir',
                'o',
                InputOption::VALUE_REQUIRED,
                'Base directory for downloads',
                self::DEFAULT_OUTPUT_DIR
            )
            ->addOption(
                'limit',
                'l',
                InputOption::VALUE_REQUIRED,
                'Maximum number of pages to process',
                0
            )
            ->addOption(
                'overwrite',
                null,
                InputOption::VALUE_NONE,
                'Overwrite existing files'
            )
            ->addOption(
                'format',
                'f',
                InputOption::VALUE_REQUIRED,
                'File naming format: slug, uid, or title',
                'slug'
            )
            ->addOption(
                'include-metadata',
                'm',
                InputOption::VALUE_NONE,
                'Include metadata as YAML frontmatter'
            )
            ->addOption(
                'flat',
                null,
                InputOption::VALUE_NONE,
                'Save all files in single directory instead of organized by site'
            )
            ->addOption(
                'dry-run',
                null,
                InputOption::VALUE_NONE,
                'Show what would be downloaded without actually doing it'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $siteIdentifier = $input->getArgument('siteIdentifier');
        $outputDir = $input->getOption('output-dir');
        $limit = (int)$input->getOption('limit');
        $overwrite = $input->getOption('overwrite');
        $format = $input->getOption('format');
        $includeMetadata = $input->getOption('include-metadata');
        $flat = $input->getOption('flat');
        $dryRun = $input->getOption('dry-run');

        $io->title('Download TYPO3 Pages as Markdown');

        if ($dryRun) {
            $io->note('DRY RUN MODE - No files will be created');
        }

        // Validate format option
        if (!in_array($format, ['slug', 'uid', 'title'], true)) {
            $io->error('Invalid format option. Must be one of: slug, uid, title');
            return Command::FAILURE;
        }

        try {
            $sites = $siteIdentifier
                ? [$this->siteFinder->getSiteByIdentifier($siteIdentifier)]
                : $this->siteFinder->getAllSites();
        } catch (SiteNotFoundException) {
            $io->error(sprintf('Site "%s" does not exist.', $siteIdentifier));
            return Command::FAILURE;
        }

        // A dry run must not leave anything behind, not even an empty directory
        $baseDir = $this->resolveOutputDirectory($outputDir);
        if (!$dryRun && !$this->createDirectory($baseDir)) {
            $io->error(sprintf('Failed to create output directory: %s', $baseDir));
            return Command::FAILURE;
        }

        $io->writeln(sprintf('Output directory: <info>%s</info>', $baseDir));

        $totalProcessed = 0;
        $totalSkipped = 0;
        $totalErrors = 0;
        // With --flat, all sites share one directory, and their file names must not collide
        $usedFilenamesByDirectory = [];

        foreach ($sites as $site) {
            $io->section(sprintf('Processing site: %s', $site->getIdentifier()));

            try {
                $pages = $this->getVisiblePages($site);
                $totalPages = count($pages);

                if ($totalPages === 0) {
                    $io->warning('No visible pages found in this site');
                    continue;
                }

                $io->writeln(sprintf('Found %d visible pages', $totalPages));

                $pagesToProcess = $limit > 0 ? array_slice($pages, 0, $limit) : $pages;

                // Create site subdirectory if not in flat mode
                $siteDir = $flat ? $baseDir : $baseDir . '/' . $site->getIdentifier();
                if (!$dryRun && !$flat) {
                    $this->ensureDirectoryExists($siteDir);
                }

                $usedFilenames = $usedFilenamesByDirectory[$siteDir] ?? [];

                if ($dryRun) {
                    $plannedFiles = [];
                    foreach ($pagesToProcess as $page) {
                        $filePath = $siteDir . '/' . $this->generateFilename($page, $format, $usedFilenames) . '.md';
                        if (!$overwrite && file_exists($filePath)) {
                            $plannedFiles[] = $filePath . ' (exists, skipped)';
                            $totalSkipped++;
                            continue;
                        }
                        $plannedFiles[] = $filePath;
                        $totalProcessed++;
                    }
                    $usedFilenamesByDirectory[$siteDir] = $usedFilenames;
                    $io->listing($plannedFiles);
                    continue;
                }

                $progressBar = $io->createProgressBar(count($pagesToProcess));
                $progressBar->start();

                foreach ($pagesToProcess as $page) {
                    try {
                        // Generate filename
                        $filename = $this->generateFilename($page, $format, $usedFilenames);
                        $filePath = $siteDir . '/' . $filename . '.md';

                        // Check if file exists and skip if not overwriting
                        if (!$overwrite && file_exists($filePath)) {
                            $totalSkipped++;
                            $progressBar->advance();
                            continue;
                        }

                        // Fetch page content
                        $content = $this->fetchPageContent($site, $page);

                        if ($content === null) {
                            $totalSkipped++;
                            $progressBar->advance();
                            continue;
                        }

                        // Add metadata frontmatter if requested
                        if ($includeMetadata) {
                            $content = $this->addFrontmatter($content, $site, $page);
                        }

                        // Write to file
                        file_put_contents($filePath, $content);
                        $totalProcessed++;
                    } catch (\Exception $e) {
                        $errorMsg = sprintf(
                            'Error processing page %d (%s): %s',
                            $page['uid'],
                            $page['title'],
                            $e->getMessage()
                        );

                        $io->newLine();
                        $io->error($errorMsg);

                        $this->logger->error('Error processing page', [
                            'page_uid' => $page['uid'],
                            'page_title' => $page['title'],
                            'error' => $e->getMessage(),
                            'exception' => $e,
                        ]);
                        $totalErrors++;
                    }

                    $progressBar->advance();
                }

                $usedFilenamesByDirectory[$siteDir] = $usedFilenames;
                $progressBar->finish();
                $io->newLine(2);
            } catch (\Exception $e) {
                $io->error(sprintf('Error processing site: %s', $e->getMessage()));
                $this->logger->error('Error processing site', [
                    'site' => $site->getIdentifier(),
                    'exception' => $e,
                ]);
                $totalErrors++;
            }
        }

        if ($dryRun) {
            $io->success(sprintf('%d pages would be exported, %d skipped', $totalProcessed, $totalSkipped));
            return $totalErrors > 0 ? Command::FAILURE : Command::SUCCESS;
        }

        $summary = sprintf(
            'Processed %d pages successfully, %d skipped, %d errors',
            $totalProcessed,
            $totalSkipped,
            $totalErrors
        );
        // Scripts and schedulers rely on the exit code to notice an incomplete export
        if ($totalErrors > 0) {
            $io->error($summary);
            return Command::FAILURE;
        }
        $io->success($summary);

        return Command::SUCCESS;
    }

    private function resolveOutputDirectory(string $dir): string
    {
        if (!PathUtility::isAbsolutePath($dir)) {
            $dir = Environment::getProjectPath() . '/' . $dir;
        }

        return $dir;
    }

    private function createDirectory(string $dir): bool
    {
        return is_dir($dir) || mkdir($dir, 0755, true) || is_dir($dir);
    }

    private function ensureDirectoryExists(string $path): void
    {
        if (!$this->createDirectory($path)) {
            throw new InvalidPathException(sprintf('Failed to create directory: %s', $path), 1769076608);
        }
    }

    /**
     * The root page of the site and every page below it that llms.txt can list
     *
     * Pages hidden in menus or excluded from indexing are left out, as in llms.txt; the
     * subpages of folders, spacers, shortcuts and the page types of the site's
     * llmsTxtExcludeDoktypes move up into their place.
     *
     * @return array<int, array<string, mixed>>
     */
    private function getVisiblePages(Site $site): array
    {
        $excludeDoktypes = $this->configurationService->getExcludeDoktypesForSite($site);
        $rootPage = $this->pageRepository->findById($site->getRootPageId());
        $pages = $rootPage !== [] ? [$rootPage] : [];

        $visitedUids = [$site->getRootPageId() => true];
        $parentUids = [$site->getRootPageId()];
        while ($parentUids !== []) {
            $nextParentUids = [];
            foreach (array_chunk($parentUids, self::PARENTS_PER_QUERY) as $parentUidsOfQuery) {
                $childrenByParent = $this->pageRepository->findNavigationByParentsBatch($parentUidsOfQuery, 0, $excludeDoktypes);
                foreach ($parentUidsOfQuery as $parentUid) {
                    foreach ($childrenByParent[$parentUid] ?? [] as $child) {
                        $uid = (int)$child['uid'];
                        if (isset($visitedUids[$uid])) {
                            continue;
                        }
                        $visitedUids[$uid] = true;
                        $pages[] = $child;
                        $nextParentUids[] = $uid;
                    }
                }
            }
            $parentUids = $nextParentUids;
        }

        return $pages;
    }

    private function fetchPageContent(Site $site, array $page): ?string
    {
        try {
            $markdownUrl = $this->urlGenerator->generatePageUrl($page);

            // Fetch markdown content from the page
            $response = $this->httpClient->request('GET', $markdownUrl);
            $statusCode = $response->getStatusCode();

            if ($statusCode === 403) {
                $this->logger->info('Skipping protected page (403 Forbidden)', [
                    'page_uid' => $page['uid'],
                    'title' => $page['title'],
                    'url' => $markdownUrl,
                ]);
                return null;
            }

            if ($statusCode >= 300 && $statusCode < 400) {
                $this->logger->info('Skipping page that redirects', [
                    'page_uid' => $page['uid'],
                    'title' => $page['title'],
                    'url' => $markdownUrl,
                    'location' => $response->getHeaderLine('Location'),
                ]);
                return null;
            }

            if ($statusCode !== 200) {
                $this->logger->warning('Failed to fetch page with status ' . $statusCode, [
                    'page_uid' => $page['uid'],
                    'title' => $page['title'],
                    'url' => $markdownUrl,
                    'status' => $statusCode,
                ]);
                return null;
            }

            $markdown = $response->getBody()->getContents();

            if (empty(trim($markdown))) {
                $this->logger->warning('Empty markdown content for page', [
                    'page_uid' => $page['uid'],
                    'url' => $markdownUrl,
                ]);
                return null;
            }

            return $markdown;
        } catch (\Exception $e) {
            $this->logger->warning('Failed to fetch page content', [
                'page_uid' => $page['uid'],
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    private function generateFilename(array $page, string $format, array &$usedFilenames): string
    {
        $baseFilename = match ($format) {
            'uid' => 'page-' . $page['uid'],
            'title' => $this->sanitizeFilename((string)$page['title']),
            // "/about-us/team" becomes "about-us-team", the root page "/" becomes "index"
            default => $this->sanitizeFilename(trim((string)($page['slug'] ?? ''), '/') ?: 'index'),
        };

        // Handle duplicates
        $filename = $baseFilename;
        $counter = 2;
        while (isset($usedFilenames[$filename])) {
            $filename = $baseFilename . '-' . $counter;
            $counter++;
        }

        $usedFilenames[$filename] = true;
        return $filename;
    }

    private function sanitizeFilename(string $filename): string
    {
        // Convert to lowercase
        $filename = mb_strtolower($filename);

        // Replace spaces and special characters with hyphens
        $filename = preg_replace('/[^a-z0-9]+/', '-', $filename);

        // Remove leading/trailing hyphens
        $filename = trim($filename, '-');

        // Limit length
        if (mb_strlen($filename) > 100) {
            $filename = mb_substr($filename, 0, 100);
        }

        // Fallback if empty
        if (empty($filename)) {
            $filename = 'page-' . time();
        }

        return $filename;
    }

    /**
     * Build YAML frontmatter for an exported page.
     *
     * Values are serialized with the YAML component rather than hand-quoted: page titles
     * and descriptions are editor-controlled, and `description` is a multi-line capable
     * text field. addslashes() neither escapes newlines - letting an editor inject
     * arbitrary frontmatter keys into every exported file - nor produces valid YAML for
     * an apostrophe, which it turns into the invalid escape sequence \' inside a
     * double-quoted scalar.
     */
    private function addFrontmatter(string $content, Site $site, array $page): string
    {
        $data = [
            'uid' => (int)$page['uid'],
            'title' => (string)$page['title'],
        ];

        if (!empty($page['description'])) {
            $data['description'] = (string)$page['description'];
        }

        $data['url'] = (string)$site->getRouter()->generateUri((int)$page['uid']);
        $data['site'] = $site->getIdentifier();
        // gmdate(), not date(): the trailing "Z" claims UTC.
        $data['exported'] = gmdate('Y-m-d\TH:i:s\Z');

        return "---\n" . Yaml::dump($data, 2, 2) . "---\n\n" . $content;
    }
}
