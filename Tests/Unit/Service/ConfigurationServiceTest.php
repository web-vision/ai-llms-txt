<?php

declare(strict_types=1);

namespace WebVision\AiLlmsTxt\Tests\Unit\Service;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Http\Uri;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;
use WebVision\AiLlmsTxt\Service\ConfigurationService;

final class ConfigurationServiceTest extends UnitTestCase
{
    private ConfigurationService $subject;
    private SiteFinder&MockObject $siteFinderMock;
    private ServerRequestInterface&MockObject $requestMock;
    private Site&MockObject $siteMock;

    protected function setUp(): void
    {
        parent::setUp();

        $this->siteFinderMock = $this->createMock(SiteFinder::class);
        $this->requestMock = $this->createMock(ServerRequestInterface::class);
        $this->siteMock = $this->createMock(Site::class);

        $this->subject = new ConfigurationService($this->siteFinderMock);
    }

    #[Test]
    public function setRequestStoresRequest(): void
    {
        $this->requestMock->method('getAttribute')->with('site')->willReturn($this->siteMock);
        $this->siteMock->method('getBase')->willReturn(new Uri('https://example.com/'));

        $this->subject->setRequest($this->requestMock);

        // If request is set, getSiteUrl should work without exception
        self::assertSame('https://example.com/', $this->subject->getSiteUrl());
    }

    #[Test]
    public function getRequestThrowsExceptionWhenNoRequestAvailable(): void
    {
        // Ensure no global request is set
        unset($GLOBALS['TYPO3_REQUEST']);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionCode(1765368301);
        $this->expectExceptionMessage('No request available');

        // This will trigger getRequest() internally
        $this->subject->getSiteUrl();
    }

    #[Test]
    public function getRequestFallsBackToGlobalRequest(): void
    {
        $this->requestMock->method('getAttribute')->with('site')->willReturn($this->siteMock);
        $this->siteMock->method('getBase')->willReturn(new Uri('https://global.example.com/'));

        $GLOBALS['TYPO3_REQUEST'] = $this->requestMock;

        // Should use global request as fallback
        self::assertSame('https://global.example.com/', $this->subject->getSiteUrl());

        unset($GLOBALS['TYPO3_REQUEST']);
    }

    #[Test]
    public function isEnabledReturnsTrueByDefault(): void
    {
        $this->setupRequestWithSiteConfiguration([]);

        self::assertTrue($this->subject->isEnabled());
    }

    #[Test]
    public function isEnabledReturnsFalseWhenDisabled(): void
    {
        $this->setupRequestWithSiteConfiguration([
            'llmsTxtEnabled' => false,
        ]);

        self::assertFalse($this->subject->isEnabled());
    }

    #[Test]
    public function isEnabledReturnsTrueWhenExplicitlyEnabled(): void
    {
        $this->setupRequestWithSiteConfiguration([
            'llmsTxtEnabled' => true,
        ]);

        self::assertTrue($this->subject->isEnabled());
    }

    #[Test]
    public function getTitleOverrideReturnsNullWhenNotSet(): void
    {
        $this->setupRequestWithSiteConfiguration([]);

        self::assertNull($this->subject->getTitleOverride());
    }

    #[Test]
    public function getTitleOverrideReturnsNullWhenEmpty(): void
    {
        $this->setupRequestWithSiteConfiguration([
            'llmsTxtTitle' => '',
        ]);

        self::assertNull($this->subject->getTitleOverride());
    }

    #[Test]
    public function getTitleOverrideReturnsTrimmedValue(): void
    {
        $this->setupRequestWithSiteConfiguration([
            'llmsTxtTitle' => '  My Custom Title  ',
        ]);

        self::assertSame('My Custom Title', $this->subject->getTitleOverride());
    }

    #[Test]
    public function getDescriptionOverrideReturnsNullWhenNotSet(): void
    {
        $this->setupRequestWithSiteConfiguration([]);

        self::assertNull($this->subject->getDescriptionOverride());
    }

    #[Test]
    public function getDescriptionOverrideReturnsTrimmedValue(): void
    {
        $this->setupRequestWithSiteConfiguration([
            'llmsTxtDescription' => '  My description  ',
        ]);

        self::assertSame('My description', $this->subject->getDescriptionOverride());
    }

    #[Test]
    public function getAdditionalInfoReturnsNullWhenNotSet(): void
    {
        $this->setupRequestWithSiteConfiguration([]);

        self::assertNull($this->subject->getAdditionalInfo());
    }

    #[Test]
    public function getAdditionalInfoReturnsTrimmedValue(): void
    {
        $this->setupRequestWithSiteConfiguration([
            'llmsTxtAdditionalInfo' => '  Additional content  ',
        ]);

        self::assertSame('Additional content', $this->subject->getAdditionalInfo());
    }

    #[Test]
    public function getContactEmailReturnsNullWhenNotSet(): void
    {
        $this->setupRequestWithSiteConfiguration([]);

        self::assertNull($this->subject->getContactEmail());
    }

    #[Test]
    public function getContactEmailReturnsTrimmedValue(): void
    {
        $this->setupRequestWithSiteConfiguration([
            'llmsTxtContactEmail' => '  contact@example.com  ',
        ]);

        self::assertSame('contact@example.com', $this->subject->getContactEmail());
    }

    #[Test]
    public function getKeywordsReturnsEmptyArrayWhenNotSet(): void
    {
        $this->setupRequestWithSiteConfiguration([]);

        self::assertSame([], $this->subject->getKeywords());
    }

    #[Test]
    public function getKeywordsReturnsEmptyArrayWhenEmpty(): void
    {
        $this->setupRequestWithSiteConfiguration([
            'llmsTxtKeywords' => '',
        ]);

        self::assertSame([], $this->subject->getKeywords());
    }

    #[Test]
    public function getKeywordsReturnsTrimmedArray(): void
    {
        $this->setupRequestWithSiteConfiguration([
            'llmsTxtKeywords' => ' TYPO3 , CMS , Extension ',
        ]);

        self::assertSame(['TYPO3', 'CMS', 'Extension'], $this->subject->getKeywords());
    }

    #[Test]
    public function getMaxDepthReturnsDefaultWhenNotSet(): void
    {
        $this->setupRequestWithSiteConfiguration([]);

        self::assertSame(2, $this->subject->getMaxDepth());
    }

    #[Test]
    public function getMaxDepthReturnsConfiguredValue(): void
    {
        $this->setupRequestWithSiteConfiguration([
            'llmsTxtMaxDepth' => 5,
        ]);

        self::assertSame(5, $this->subject->getMaxDepth());
    }

    #[Test]
    public function getMaxDepthCastsToInteger(): void
    {
        $this->setupRequestWithSiteConfiguration([
            'llmsTxtMaxDepth' => '3',
        ]);

        self::assertSame(3, $this->subject->getMaxDepth());
    }

    #[Test]
    public function getSiteNameReturnsSiteIdentifier(): void
    {
        $this->requestMock->method('getAttribute')->with('site')->willReturn($this->siteMock);
        $this->siteMock->method('getIdentifier')->willReturn('my-site');

        $this->subject->setRequest($this->requestMock);

        self::assertSame('my-site', $this->subject->getSiteName());
    }

    #[Test]
    public function getSiteUrlReturnsSiteBaseUri(): void
    {
        $this->requestMock->method('getAttribute')->with('site')->willReturn($this->siteMock);
        $this->siteMock->method('getBase')->willReturn(new Uri('https://www.example.com/subdir/'));

        $this->subject->setRequest($this->requestMock);

        self::assertSame('https://www.example.com/subdir/', $this->subject->getSiteUrl());
    }

    /**
     * Helper to set up request with site configuration
     */
    private function setupRequestWithSiteConfiguration(array $configuration): void
    {
        $this->requestMock->method('getAttribute')->with('site')->willReturn($this->siteMock);
        $this->siteMock->method('getConfiguration')->willReturn($configuration);

        $this->subject->setRequest($this->requestMock);
    }
}
