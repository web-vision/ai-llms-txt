<?php

declare(strict_types=1);

namespace WebVision\AiLlmsTxt\Tests\Unit\Service;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use TYPO3\CMS\Core\Routing\RouterInterface;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Site\Entity\SiteLanguage;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;
use WebVision\AiLlmsTxt\Service\UrlGeneratorService;

final class UrlGeneratorServiceTest extends UnitTestCase
{
    private SiteFinder&MockObject $siteFinderMock;
    private Site&MockObject $siteMock;
    private RouterInterface&MockObject $routerMock;
    private SiteLanguage&MockObject $siteLanguageMock;
    private UrlGeneratorService $subject;

    protected function setUp(): void
    {
        parent::setUp();

        $this->siteFinderMock = $this->createMock(SiteFinder::class);
        $this->siteMock = $this->createMock(Site::class);
        $this->routerMock = $this->createMock(RouterInterface::class);
        $this->siteLanguageMock = $this->createMock(SiteLanguage::class);

        $this->siteMock->method('getRouter')->willReturn($this->routerMock);
        $this->siteMock->method('getDefaultLanguage')->willReturn($this->siteLanguageMock);
        $this->siteFinderMock->method('getSiteByPageId')->willReturn($this->siteMock);

        $this->subject = new UrlGeneratorService($this->siteFinderMock);
    }

    #[Test]
    public function generatePageUrlAppendsMdToStandardUrl(): void
    {
        $uriMock = $this->createMock(\Psr\Http\Message\UriInterface::class);
        $uriMock->method('__toString')->willReturn('https://www.mysite.de/some/page');

        $this->routerMock->method('generateUri')
            ->willReturn($uriMock);

        $result = $this->subject->generatePageUrl(['uid' => 123]);

        static::assertSame('https://www.mysite.de/some/page.md', $result);
    }

    #[Test]
    public function generatePageUrlRemovesTrailingSlashBeforeAppendingMd(): void
    {
        $uriMock = $this->createMock(\Psr\Http\Message\UriInterface::class);
        $uriMock->method('__toString')->willReturn('https://www.mysite.de/some/page/');

        $this->routerMock->method('generateUri')
            ->willReturn($uriMock);

        $result = $this->subject->generatePageUrl(['uid' => 123]);

        static::assertSame('https://www.mysite.de/some/page.md', $result);
    }

    #[Test]
    public function generatePageUrlRemovesMultipleTrailingSlashesBeforeAppendingMd(): void
    {
        $uriMock = $this->createMock(\Psr\Http\Message\UriInterface::class);
        $uriMock->method('__toString')->willReturn('https://www.mysite.de/some/page///');

        $this->routerMock->method('generateUri')
            ->willReturn($uriMock);

        $result = $this->subject->generatePageUrl(['uid' => 123]);

        static::assertSame('https://www.mysite.de/some/page.md', $result);
    }

    #[Test]
    public function generatePageUrlWorksWithRootDomainAndSlash(): void
    {
        $uriMock = $this->createMock(\Psr\Http\Message\UriInterface::class);
        $uriMock->method('__toString')->willReturn('https://www.mysite.de/');

        $this->routerMock->method('generateUri')
            ->willReturn($uriMock);

        $result = $this->subject->generatePageUrl(['uid' => 1]);

        static::assertSame('https://www.mysite.de/index.md', $result);
    }
}
