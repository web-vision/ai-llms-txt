<?php

declare(strict_types=1);

namespace WebVision\AiLlmsTxt\Command;

use GuzzleHttp\Client;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Resource\Exception\InvalidPathException;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Utility\PathUtility;
use WebVision\AiLlmsTxt\Repository\PageRepository;
use WebVision\AiLlmsTxt\Service\MarkdownConverterService;
use WebVision\AiLlmsTxt\Service\UrlGeneratorService;

/**
 * Command to download TYPO3 pages as Markdown files
 */
final class DownloadMarkdownCommand extends Command
{
    private const DEFAULT_OUTPUT_DIR = 'var/markdown-export';
    private readonly Client $httpClient;

    public function __construct(
        private readonly SiteFinder $siteFinder,
        private readonly PageRepository $pageRepository,
        private readonly MarkdownConverterService $markdownConverter,
        private readonly UrlGeneratorService $urlGenerator,
        private readonly LoggerInterface $logger
    ) {
        parent::__construct();
        $this->httpClient = new Client([
            'timeout' => 30,
            'http_errors' => false,
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

        // Prepare output directory
        $baseDir = $this->prepareOutputDirectory($outputDir);
        if ($baseDir === null) {
            $io->error(sprintf('Failed to create output directory: %s', $outputDir));
            return Command::FAILURE;
        }

        $io->writeln(sprintf('Output directory: <info>%s</info>', $baseDir));

        // Get sites to process
        $sites = $siteIdentifier
            ? [$this->siteFinder->getSiteByIdentifier($siteIdentifier)]
            : $this->siteFinder->getAllSites();

        $totalProcessed = 0;
        $totalSkipped = 0;
        $totalErrors = 0;

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
                $progressBar = $io->createProgressBar(count($pagesToProcess));
                $progressBar->start();

                // Create site subdirectory if not in flat mode
                $siteDir = $flat ? $baseDir : $baseDir . '/' . $site->getIdentifier();
                if (!$dryRun && !$flat) {
                    $this->ensureDirectoryExists($siteDir);
                }

                $usedFilenames = [];

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

                        if ($dryRun) {
                            $totalProcessed++;
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

                $progressBar->finish();
                $io->newLine(2);
            } catch (\Exception $e) {
                $io->error(sprintf('Error processing site: %s', $e->getMessage()));
                $this->logger->error('Error processing site', [
                    'site' => $site->getIdentifier(),
                    'exception' => $e,
                ]);
            }
        }

        $io->success(sprintf(
            'Processed %d pages successfully, %d skipped, %d errors',
            $totalProcessed,
            $totalSkipped,
            $totalErrors
        ));

        return Command::SUCCESS;
    }

    private function prepareOutputDirectory(string $dir): ?string
    {
        // Convert relative path to absolute
        if (!PathUtility::isAbsolutePath($dir)) {
            $dir = Environment::getProjectPath() . '/' . $dir;
        }

        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            return null;
        }

        return $dir;
    }

    private function ensureDirectoryExists(string $path): void
    {
        if (!is_dir($path) && !mkdir($path, 0755, true) && !is_dir($path)) {
            throw new InvalidPathException(sprintf('Failed to create directory: %s', $path));
        }
    }

    private function getVisiblePages(Site $site): array
    {
        return $this->pageRepository->findNavigationByParent($site->getRootPageId());
    }

    private function fetchPageContent(Site $site, array $page): ?string
    {
        try {
            // Use .md suffix to get markdown content (typeNum 1701)
            $uri = (string)$site->getRouter()->generateUri((int)$page['uid']);
            $markdownUrl = rtrim($uri, '/') . '.md';

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
            'title' => $this->sanitizeFilename($page['title']),
            default => $this->sanitizeFilename($page['title']), // slug is default, but we use title as fallback
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

    private function addFrontmatter(string $content, Site $site, array $page): string
    {
        $uri = $site->getRouter()->generateUri((int)$page['uid']);

        $frontmatter = "---\n";
        $frontmatter .= 'uid: ' . $page['uid'] . "\n";
        $frontmatter .= 'title: "' . addslashes($page['title']) . "\"\n";

        if (!empty($page['description'])) {
            $frontmatter .= 'description: "' . addslashes($page['description']) . "\"\n";
        }

        $frontmatter .= 'url: "' . $uri . "\"\n";
        $frontmatter .= 'site: "' . $site->getIdentifier() . "\"\n";
        $frontmatter .= 'exported: "' . date('Y-m-d\TH:i:s\Z') . "\"\n";
        $frontmatter .= "---\n\n";

        return $frontmatter . $content;
    }
}
