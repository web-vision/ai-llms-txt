<?php

declare(strict_types=1);

namespace WebVision\AiLlmsTxt\Controller;

use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Information\Typo3Version;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;
use WebVision\AiLlmsTxt\Repository\PageRepository;
use WebVision\AiLlmsTxt\Service\ConfigurationService;
use WebVision\AiLlmsTxt\Service\LlmsTxtGeneratorService;
use WebVision\AiLlmsTxt\Service\MarkdownConverterService;

/**
 * Controller for serving llms.txt content via TypoScript PAGE object
 *
 * This controller is kept thin - it only handles HTTP request/response
 * and delegates all business logic to dedicated service classes.
 */
class LlmsTxtController
{
    public function __construct(
        private readonly LlmsTxtGeneratorService $llmsTxtGenerator,
        private readonly ConfigurationService $configurationService,
        private readonly MarkdownConverterService $markdownConverter,
        private readonly PageRepository $pageRepository
    ) {
    }

    /**
     * Generate llms.txt content for TypoScript USER object
     */
    #[\TYPO3\CMS\Core\Attribute\AsAllowedCallable]
    public function generateAction(string $content, array $conf, ServerRequestInterface $request): string
    {
        try {
            $currentPageId = $this->getCurrentPageId($request);
            return $this->llmsTxtGenerator->generateLlmsTxt($currentPageId);
        } catch (\Exception $e) {
            // Return error message in llms.txt format
            return "llmstxt: 1.0\nsite: " . $this->configurationService->getSiteUrl() . "\nerror: Failed to generate content\n";
        }
    }

    /**
     * Render current page as Markdown by leveraging TYPO3's frontend rendering
     * This approach uses TYPO3's normal rendering pipeline to get ALL content
     * from all column positions (colPos 0, 1, 100+)
     */
    #[\TYPO3\CMS\Core\Attribute\AsAllowedCallable]
    public function renderPageAsMarkdown(string $content, array $conf, ServerRequestInterface $request): string
    {
        try {
            $pageHtml = $this->getRenderedPageContent($request);

            if (empty($pageHtml)) {
                return "# Error\n\nNo page content could be rendered.\n";
            }

            $markdown = $this->markdownConverter->convertHtmlToMarkdown($pageHtml);

            if (empty(trim($markdown))) {
                return "# Error\n\nPage rendered but conversion to Markdown failed.\n";
            }

            return $markdown;

        } catch (\Exception $e) {
            return "# Error\n\nFailed to render page: " . $e->getMessage() . "\n";
        }
    }

    /**
     * Get the fully rendered page content from TYPO3's frontend rendering
     * This captures ALL content elements from all column positions
     */
    protected function getRenderedPageContent(ServerRequestInterface $request): string
    {

        $cObject = GeneralUtility::makeInstance(ContentObjectRenderer::class);
        $cObject->start([], 'pages');

        $pageId = $this->getCurrentPageId($request);

        $page = $this->pageRepository->findById($pageId);

        $html = '';

        if (!empty($page['title'])) {
            $html .= '<h1>' . htmlspecialchars($page['title']) . '</h1>';
        }

        if (!empty($page['description'])) {
            $html .= '<p class="page-description"> > ' . htmlspecialchars($page['description']) . '</p>';
        }

        // Render ALL content elements from ALL column positions
        // This ensures we capture everything on the page even third-party extensions
        $contentConfiguration = [
            'table' => 'tt_content',
            'select.' => [
                'orderBy' => 'colPos, sorting',
                'where' => '{#deleted}=0 AND {#hidden}=0',
                'pidInList' => (string)$pageId,
            ],
        ];
        $renderedContent = $cObject->cObjGetSingle('CONTENT', $contentConfiguration);

        if (!empty($renderedContent)) {
            $html .= $renderedContent;
        }

        return $html;
    }

    protected function getCurrentPageId(ServerRequestInterface $request): int
    {
        $version =  (string)GeneralUtility::makeInstance(Typo3Version::class)->getMajorVersion();

        if (version_compare($version, '14', '<')) {
            // @extensionScannerIgnoreLine
            if (isset($GLOBALS['TSFE']) && isset($GLOBALS['TSFE']->id)) {
                // @extensionScannerIgnoreLine
                return (int)$GLOBALS['TSFE']->id;
            }
            throw new \RuntimeException('Could not determine current page ID in TYPO3 v12 context.', 1765368300);
        }

        return (int)$request->getAttribute('frontend.page.information')->getId();
    }
}
