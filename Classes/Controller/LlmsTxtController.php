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
use TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController;
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
            $this->applyCacheLifetimeToPageCache($request);
            return $this->llmsTxtGenerator->generateLlmsTxt($currentPageId);
        } catch (\Exception $e) {
            $this->logger->error('Failed to generate llms.txt content', ['exception' => $e]);
            $this->disablePageCache($request, 'llms.txt generation failed');

            // Return generic error message in llms.txt format - never leak exception details
            return "llmstxt: 1.0\nsite: " . $this->configurationService->getSiteUrl() . "\nerror: Failed to generate content\n";
        }
    }

    /**
     * Apply llmsTxtCacheLifetime to TYPO3's page cache, which stores the llms.txt response.
     *
     * Both page types are cached USER objects, so TYPO3 keeps the rendered llms.txt in its
     * page cache regardless of the extension's own cache. That entry belongs to the site
     * root page, and TYPO3 only clears it when the root page itself changes: edits further
     * down the tree would stay invisible for the whole page cache lifetime (a day by
     * default). Tagging the entry "pages" lets DataHandler clear it whenever any page record
     * is saved, and capping the page's cache timeout limits how long it is kept. A lifetime
     * of 0 keeps llms.txt out of the page cache entirely, so it really is generated on every
     * request.
     */
    protected function applyCacheLifetimeToPageCache(ServerRequestInterface $request): void
    {
        $cacheLifetime = $this->configurationService->getCacheLifetime();
        if ($cacheLifetime <= 0) {
            $this->disablePageCache($request, 'llms.txt caching is disabled (llmsTxtCacheLifetime)');
            return;
        }

        $frontendController = $this->getFrontendController($request);
        if ($frontendController === null) {
            return;
        }
        $frontendController->addCacheTags(['pages']);

        // TYPO3 12 has no lifetime per cache tag. The page cache entry gets the lifetime of
        // the page record's cache_timeout instead, which TSFE only reads once rendering is
        // done - so lowering it here still applies to this entry. A shorter timeout set by
        // an editor is kept.
        if (is_array($frontendController->page)) {
            $pageCacheTimeout = (int)($frontendController->page['cache_timeout'] ?? 0);
            if ($pageCacheTimeout <= 0 || $pageCacheTimeout > $cacheLifetime) {
                $frontendController->page['cache_timeout'] = $cacheLifetime;
            }
        }
    }

    /**
     * Keep the current response out of TYPO3's page cache.
     *
     * Used for error responses in particular: cached, a transient failure would keep being
     * served as an error message for the full page cache lifetime.
     */
    protected function disablePageCache(ServerRequestInterface $request, string $reason): void
    {
        // An internal request, so that $TYPO3_CONF_VARS['FE']['disableNoCacheParameter']
        // cannot veto it: that setting is meant for the no_cache URL parameter only.
        $this->getFrontendController($request)?->set_no_cache('EXT:ai_llms_txt: ' . $reason, true);
    }

    protected function getFrontendController(ServerRequestInterface $request): ?TypoScriptFrontendController
    {
        $frontendController = $request->getAttribute('frontend.controller') ?? $GLOBALS['TSFE'] ?? null;

        return $frontendController instanceof TypoScriptFrontendController ? $frontendController : null;
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
                $this->disablePageCache($request, 'Markdown conversion failed');
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
            $this->disablePageCache($request, 'Markdown rendering failed');

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
