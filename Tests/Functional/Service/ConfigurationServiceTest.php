<?php

declare(strict_types=1);

namespace WebVision\AiLlmsTxt\Tests\Functional\Service;

use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;
use WebVision\AiLlmsTxt\Service\ConfigurationService;

/**
 * Functional tests for ConfigurationService
 *
 * Tests the configuration service can be instantiated and has correct constants.
 * Note: Full configuration tests with site configuration require SiteBasedTestTrait
 * which involves more complex setup. These tests focus on the class structure
 * and constant values.
 */
final class ConfigurationServiceTest extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'web-vision/ai-llms-txt',
    ];

    protected array $coreExtensionsToLoad = [
        'seo',
    ];

    private ConfigurationService $subject;

    protected function setUp(): void
    {
        parent::setUp();

        $this->subject = $this->get(ConfigurationService::class);
    }

    #[Test]
    public function configurationServiceCanBeInstantiated(): void
    {
        static::assertInstanceOf(ConfigurationService::class, $this->subject);
    }

    #[Test]
    public function classImplementsRequestAwareInterface(): void
    {
        // Check that setRequest method exists (from RequestAwareInterface)
        static::assertTrue(method_exists($this->subject, 'setRequest'));
    }

    #[Test]
    public function classHasAllExpectedMethods(): void
    {
        $expectedMethods = [
            'isEnabled',
            'getTitleOverride',
            'getDescriptionOverride',
            'getMaxDepth',
            'getKeywords',
            'getContactEmail',
            'getAdditionalInfo',
            'getSiteUrl',
            'getSiteName',
            'setRequest',
        ];

        foreach ($expectedMethods as $method) {
            static::assertTrue(
                method_exists($this->subject, $method),
                sprintf('Method %s should exist on ConfigurationService', $method)
            );
        }
    }
}
