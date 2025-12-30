<?php

declare(strict_types=1);

namespace WebVision\AiLlmsTxt\Service;

use TYPO3\CMS\Core\Site\SiteFinder;
use WebVision\AiLlmsTxt\Builder\NavigationBuilder;
use WebVision\AiLlmsTxt\Repository\PageRepository;

/**
 * Application Service for generating llms.txt content
 * Orchestrates repositories, builders, and other services to build the final output
 */
class LlmsTxtGeneratorService
{
    public function __construct(
        private readonly ConfigurationService $configurationService,
        private readonly PageRepository $pageRepository,
        private readonly NavigationBuilder $navigationBuilder,
        private readonly SiteFinder $siteFinder
    ) {
    }

    /**
     * Generate complete llms.txt content
     */
    public function generateLlmsTxt(int $currentPageId): string
    {
        $lines = [];

        if (!$this->configurationService->isEnabled()) {
            return "# LLMS.TXT generation is disabled for this site\n";
        }

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
        $navigationStructure = $this->navigationBuilder->build(
            $site->getRootPageId(),
            $maxDepth
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
}
