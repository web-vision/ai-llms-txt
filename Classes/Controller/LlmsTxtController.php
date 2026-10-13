<?php

declare(strict_types=1);

namespace WebVision\AiLlmsTxt\Controller;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use TYPO3\CMS\Core\Error\Http\StatusException;
use TYPO3\CMS\Core\Http\PropagateResponseException;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;
use TYPO3\CMS\Frontend\Controller\ErrorController;
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
        private readonly PageRepository $pageRepository,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Generate llms.txt content for TypoScript USER object
     */
    #[\TYPO3\CMS\Core\Attribute\AsAllowedCallable]
    public function generateAction(string $content, array $conf, ServerRequestInterface $request): string
    {
        $this->configurationService->setRequest($request);

        try {
            $currentPageId = $this->configurationService->getCurrentPageId();
            return $this->llmsTxtGenerator->generateLlmsTxt($currentPageId);
        } catch (\Exception $e) {
            $this->logger->error('Failed to generate llms.txt content', ['exception' => $e]);

            // Return generic error message in llms.txt format - never leak exception details
            return "llmstxt: 1.0\nsite: " . $this->configurationService->getSiteUrl() . "\nerror: Failed to generate content\n";
        }
    }

    /**
     * Render current page as Markdown by leveraging TYPO3's frontend rendering
     * This approach uses TYPO3's normal rendering pipeline to get ALL content
     * from all column positions (colPos 0, 1, 100+)
     *
     * @throws PropagateResponseException if Markdown output is not allowed for this page
     */
    #[\TYPO3\CMS\Core\Attribute\AsAllowedCallable]
    public function renderPageAsMarkdown(string $content, array $conf, ServerRequestInterface $request): string
    {
        $this->configurationService->setRequest($request);

        try {
            $pageId = $this->configurationService->getCurrentPageId();
            $page = $this->pageRepository->findById($pageId, $request->getAttribute('language'));

            $this->assertMarkdownOutputIsAllowed($request, $page);

            $pageHtml = $this->getRenderedPageContent($request, $pageId, $page);

            if (empty($pageHtml)) {
                return "# Error\n\nNo page content could be rendered.\n";
            }

            $markdown = $this->markdownConverter->convertHtmlToMarkdown($pageHtml);

            if (empty(trim($markdown))) {
                return "# Error\n\nPage rendered but conversion to Markdown failed.\n";
            }

            return $markdown;

        } catch (PropagateResponseException|StatusException $e) {
            // Access decision - must reach the middleware stack, not be turned into a body.
            // StatusException covers ErrorController's fallback of throwing a
            // PageNotFoundException when it cannot render an error page itself.
            throw $e;
        } catch (\Exception $e) {
            $this->logger->error('Failed to render page as Markdown', ['exception' => $e]);

            // Generic message only - exception messages can carry SQL statements and
            // absolute filesystem paths, and this endpoint is publicly reachable.
            return "# Error\n\nFailed to render page.\n";
        }
    }

    /**
     * Guard the Markdown endpoint against serving content it must not expose.
     *
     * Without this the `.md` representation stays fully readable even when the site
     * has switched the feature off, and for pages that are explicitly excluded from
     * indexing - neither of which is signalled anywhere in the plain text response.
     *
     * @param array<string, mixed> $page Page record as returned by PageRepository::findById()
     * @throws PropagateResponseException
     */
    protected function assertMarkdownOutputIsAllowed(ServerRequestInterface $request, array $page): void
    {
        if (!$this->configurationService->isEnabled()) {
            throw $this->pageNotFound($request, 'Markdown output is disabled for this site.');
        }

        // Pages flagged no_index are already kept out of llms.txt by PageRepository;
        // serving them as Markdown would reintroduce exactly what the flag forbids.
        if (!empty($page['no_index'])) {
            throw $this->pageNotFound($request, 'Markdown output is not available for pages excluded from indexing.');
        }
    }

    protected function pageNotFound(ServerRequestInterface $request, string $message): PropagateResponseException
    {
        return new PropagateResponseException(
            GeneralUtility::makeInstance(ErrorController::class)->pageNotFoundAction($request, $message),
            1769076609
        );
    }

    /**
     * Get the fully rendered page content from TYPO3's frontend rendering
     * This captures ALL content elements from all column positions
     *
     * @param array<string, mixed> $page Page record as returned by PageRepository::findById()
     */
    protected function getRenderedPageContent(ServerRequestInterface $request, int $pageId, array $page): string
    {
        $cObject = GeneralUtility::makeInstance(ContentObjectRenderer::class);
        $cObject->setRequest($request);
        $cObject->start([], 'pages');

        $html = '';

        $displayTitle = !empty($page['seo_title']) ? $page['seo_title'] : ($page['title'] ?? '');
        if (!empty($displayTitle)) {
            $html .= '<h1>' . htmlspecialchars($displayTitle) . '</h1>';
        }

        if (!empty($page['description'])) {
            $html .= '<p class="page-description"> > ' . htmlspecialchars($page['description']) . '</p>';
        }

        // Render ALL content elements from ALL column positions
        // This ensures we capture everything on the page even third-party extensions
        $where = '{#deleted}=0 AND {#hidden}=0';
        // b13/container renders the children of a container inside the container. Rendered on
        // their own as well, as elements of their column position, they would appear twice.
        // TcaSchemaFactory is not available in TYPO3 12, so the TCA is checked directly.
        if (isset($GLOBALS['TCA']['tt_content']['columns']['tx_container_parent'])) {
            $where .= ' AND {#tx_container_parent}=0';
        }
        $contentConfiguration = [
            'table' => 'tt_content',
            'select.' => [
                'orderBy' => 'colPos, sorting',
                'where' => $where,
                'pidInList' => (string)$pageId,
            ],
        ];
        $renderedContent = $cObject->cObjGetSingle('CONTENT', $contentConfiguration);

        if (!empty($renderedContent)) {
            $html .= $renderedContent;
        }

        return $html;
    }
}
