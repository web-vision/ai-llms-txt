<?php

declare(strict_types=1);

namespace WebVision\AiLlmsTxt\Tests\Unit\Controller;

use PHPUnit\Framework\Attributes\Test;
use Psr\Log\NullLogger;
use TYPO3\CMS\Core\Http\PropagateResponseException;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;
use WebVision\AiLlmsTxt\Repository\PageRepository;
use WebVision\AiLlmsTxt\Service\ConfigurationService;
use WebVision\AiLlmsTxt\Service\LlmsTxtGeneratorService;
use WebVision\AiLlmsTxt\Service\MarkdownConverterService;
use WebVision\AiLlmsTxt\Tests\Unit\Controller\Fixtures\GateAccessibleLlmsTxtController;

/**
 * Unit tests for the access gate on the Markdown endpoint.
 *
 * The Markdown endpoint is publicly reachable both via the `.md` route enhancer and via
 * `?type=1701`, so it must apply the site's own opt-out signals itself - it cannot rely
 * on the llms.txt code path having done so.
 */
final class LlmsTxtControllerTest extends UnitTestCase
{
    #[Test]
    public function markdownOutputIsRejectedWhenDisabledForTheSite(): void
    {
        $subject = $this->createSubject(enabled: false);

        $this->expectException(PropagateResponseException::class);

        $subject->runGate(new ServerRequest(), ['uid' => 5, 'no_index' => 0]);
    }

    #[Test]
    public function markdownOutputIsRejectedForPagesExcludedFromIndexing(): void
    {
        $subject = $this->createSubject(enabled: true);

        $this->expectException(PropagateResponseException::class);

        $subject->runGate(new ServerRequest(), ['uid' => 5, 'no_index' => 1]);
    }

    #[Test]
    public function markdownOutputIsAllowedForAnEnabledSiteAndIndexablePage(): void
    {
        $subject = $this->createSubject(enabled: true);

        $subject->runGate(new ServerRequest(), ['uid' => 5, 'no_index' => 0]);

        static::assertTrue(true, 'Gate must not reject a normal, indexable page');
    }

    private function createSubject(bool $enabled): GateAccessibleLlmsTxtController
    {
        $configurationService = $this->createMock(ConfigurationService::class);
        $configurationService->method('isEnabled')->willReturn($enabled);

        return new GateAccessibleLlmsTxtController(
            $this->createMock(LlmsTxtGeneratorService::class),
            $configurationService,
            $this->createMock(MarkdownConverterService::class),
            $this->createMock(PageRepository::class),
            new NullLogger()
        );
    }
}
