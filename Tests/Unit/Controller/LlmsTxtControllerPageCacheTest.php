<?php

declare(strict_types=1);

namespace WebVision\AiLlmsTxt\Tests\Unit\Controller;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\NullLogger;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;
use WebVision\AiLlmsTxt\Controller\LlmsTxtController;
use WebVision\AiLlmsTxt\Repository\PageRepository;
use WebVision\AiLlmsTxt\Service\ConfigurationService;
use WebVision\AiLlmsTxt\Service\LlmsTxtGeneratorService;
use WebVision\AiLlmsTxt\Service\MarkdownConverterService;

/**
 * Unit tests for how the controller steers TYPO3's page cache.
 *
 * Both page types are cached USER objects, so whatever they return lands in the page
 * cache unless the controller says otherwise - independent of the extension's own cache.
 * In TYPO3 12 the page cache is controlled through TypoScriptFrontendController.
 */
final class LlmsTxtControllerPageCacheTest extends UnitTestCase
{
    private TypoScriptFrontendController&MockObject $frontendController;
    private ServerRequest $request;

    protected function setUp(): void
    {
        parent::setUp();
        $this->frontendController = $this->createMock(TypoScriptFrontendController::class);
        $this->frontendController->page = ['uid' => 1, 'cache_timeout' => 0];
        $this->request = (new ServerRequest())->withAttribute('frontend.controller', $this->frontendController);
    }

    #[Test]
    public function llmsTxtIsKeptOutOfThePageCacheWhenNoCacheLifetimeIsConfigured(): void
    {
        $generator = $this->createMock(LlmsTxtGeneratorService::class);
        $generator->method('generateLlmsTxt')->willReturn("# Site\n");

        $this->frontendController->expects(static::once())->method('set_no_cache')->with(static::anything(), true);
        $this->frontendController->expects(static::never())->method('addCacheTags');

        $this->createSubject(cacheLifetime: 0, generator: $generator)->generateAction('', [], $this->request);
    }

    #[Test]
    public function llmsTxtPageCacheEntryIsTaggedPagesWithTheConfiguredLifetime(): void
    {
        $generator = $this->createMock(LlmsTxtGeneratorService::class);
        $generator->method('generateLlmsTxt')->willReturn("# Site\n");

        $this->frontendController->expects(static::never())->method('set_no_cache');
        $this->frontendController->expects(static::once())->method('addCacheTags')->with(['pages']);

        $this->createSubject(cacheLifetime: 900, generator: $generator)->generateAction('', [], $this->request);

        static::assertSame(900, $this->frontendController->page['cache_timeout']);
    }

    #[Test]
    public function shorterCacheTimeoutOfThePageIsKept(): void
    {
        $generator = $this->createMock(LlmsTxtGeneratorService::class);
        $generator->method('generateLlmsTxt')->willReturn("# Site\n");
        $this->frontendController->page['cache_timeout'] = 60;

        $this->createSubject(cacheLifetime: 900, generator: $generator)->generateAction('', [], $this->request);

        static::assertSame(60, $this->frontendController->page['cache_timeout']);
    }

    #[Test]
    public function llmsTxtErrorResponseIsNotPageCached(): void
    {
        $generator = $this->createMock(LlmsTxtGeneratorService::class);
        $generator->method('generateLlmsTxt')->willThrowException(new \RuntimeException('database gone', 1769076610));

        $this->frontendController->expects(static::once())->method('set_no_cache')->with(static::anything(), true);

        $result = $this->createSubject(cacheLifetime: 900, generator: $generator)->generateAction('', [], $this->request);

        static::assertStringContainsString('error: Failed to generate content', $result);
    }

    #[Test]
    public function markdownErrorResponseIsNotPageCached(): void
    {
        $pageRepository = $this->createMock(PageRepository::class);
        $pageRepository->method('findById')->willThrowException(new \RuntimeException('database gone', 1769076611));

        $this->frontendController->expects(static::once())->method('set_no_cache')->with(static::anything(), true);

        $result = $this->createSubject(cacheLifetime: 0, pageRepository: $pageRepository)->renderPageAsMarkdown('', [], $this->request);

        static::assertSame("# Error\n\nFailed to render page.\n", $result);
    }

    private function createSubject(
        int $cacheLifetime,
        ?LlmsTxtGeneratorService $generator = null,
        ?PageRepository $pageRepository = null
    ): LlmsTxtController {
        $configurationService = $this->createMock(ConfigurationService::class);
        $configurationService->method('isEnabled')->willReturn(true);
        $configurationService->method('getCurrentPageId')->willReturn(1);
        $configurationService->method('getCacheLifetime')->willReturn($cacheLifetime);
        $configurationService->method('getSiteUrl')->willReturn('https://example.com/');

        return new LlmsTxtController(
            $generator ?? $this->createMock(LlmsTxtGeneratorService::class),
            $configurationService,
            $this->createMock(MarkdownConverterService::class),
            $pageRepository ?? $this->createMock(PageRepository::class),
            new NullLogger()
        );
    }
}
