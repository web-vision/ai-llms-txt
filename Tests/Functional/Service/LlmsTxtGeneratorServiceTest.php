<?php

declare(strict_types=1);

namespace WebVision\AiLlmsTxt\Tests\Functional\Service;

use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;
use WebVision\AiLlmsTxt\Service\LlmsTxtGeneratorService;

/**
 * Functional tests for LlmsTxtGeneratorService
 *
 * Tests the service can be instantiated.
 * Note: Full integration tests with site configuration require SiteBasedTestTrait
 * which involves more complex setup. These tests focus on the class structure.
 */
final class LlmsTxtGeneratorServiceTest extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'web-vision/ai-llms-txt',
    ];

    protected array $coreExtensionsToLoad = [
        'seo',
    ];

    private LlmsTxtGeneratorService $subject;

    protected function setUp(): void
    {
        parent::setUp();

        $this->subject = $this->get(LlmsTxtGeneratorService::class);
    }

    #[Test]
    public function llmsTxtGeneratorServiceCanBeInstantiated(): void
    {
        static::assertInstanceOf(LlmsTxtGeneratorService::class, $this->subject);
    }

    #[Test]
    public function classHasGenerateLlmsTxtMethod(): void
    {
        static::assertTrue(method_exists($this->subject, 'generateLlmsTxt'));
    }
}
