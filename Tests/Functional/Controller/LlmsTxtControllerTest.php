<?php

declare(strict_types=1);

namespace WebVision\AiLlmsTxt\Tests\Functional\Controller;

use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;
use WebVision\AiLlmsTxt\Controller\LlmsTxtController;

/**
 * The controller is instantiated by TYPO3's userFunc mechanism through the DI container,
 * so a constructor dependency that cannot be autowired only surfaces at request time.
 */
final class LlmsTxtControllerTest extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'web-vision/ai-llms-txt',
    ];

    protected array $coreExtensionsToLoad = [
        'seo',
    ];

    #[Test]
    public function controllerIsResolvableFromTheContainer(): void
    {
        static::assertInstanceOf(LlmsTxtController::class, $this->get(LlmsTxtController::class));
    }
}
