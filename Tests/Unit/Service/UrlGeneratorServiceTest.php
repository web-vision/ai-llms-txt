<?php

declare(strict_types=1);

namespace WebVision\AiLlmsTxt\Tests\Unit\Service;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use TYPO3\CMS\Core\Http\Uri;
use TYPO3\CMS\Core\Routing\RouterInterface;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Site\Entity\SiteLanguage;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;
use WebVision\AiLlmsTxt\Service\UrlGeneratorService;

final class UrlGeneratorServiceTest extends UnitTestCase
{
    private Site&MockObject $siteMock;
    private RouterInterface&MockObject $routerMock;
    private SiteLanguage&MockObject $siteLanguageMock;
    private UrlGeneratorService $subject;

    protected function setUp(): void
    {
        parent::setUp();

        $siteFinderMock = $this->createMock(SiteFinder::class);
        $this->siteMock = $this->createMock(Site::class);
        $this->routerMock = $this->createMock(RouterInterface::class);
        $this->siteLanguageMock = $this->createMock(SiteLanguage::class);

        $this->siteMock->method('getRouter')->willReturn($this->routerMock);
        $this->siteMock->method('getDefaultLanguage')->willReturn($this->siteLanguageMock);
        $siteFinderMock->method('getSiteByPageId')->willReturn($this->siteMock);

        $this->subject = new UrlGeneratorService($siteFinderMock);
    }

    #[Test]
    public function generatePageUrlAsksTheRouterForTheMarkdownPageType(): void
    {
        $this->routerMock->expects(static::once())->method('generateUri')
            ->with(123, ['_language' => 2, 'type' => 1701], '', RouterInterface::ABSOLUTE_URL)
            ->willReturn(new Uri('https://www.mysite.de/some/page.md'));

        $result = $this->subject->generatePageUrl(['uid' => 123, 'sys_language_uid' => 2]);

        static::assertSame('https://www.mysite.de/some/page.md', $result);
    }

    #[Test]
    public function generatePageUrlUsesTheDefaultLanguageForPagesWithoutLanguage(): void
    {
        $this->routerMock->expects(static::once())->method('generateUri')
            ->with(1, ['_language' => $this->siteLanguageMock, 'type' => 1701], '', RouterInterface::ABSOLUTE_URL)
            ->willReturn(new Uri('https://www.mysite.de/index.md'));

        static::assertSame('https://www.mysite.de/index.md', $this->subject->generatePageUrl(['uid' => 1]));
    }

    #[Test]
    public function generatePageUrlKeepsTheUrlOfTheRouterUnchanged(): void
    {
        // Without the route enhancer, the router addresses the page type as a parameter
        $this->routerMock->method('generateUri')->willReturn(new Uri('https://www.mysite.de/some/page/?type=1701'));

        static::assertSame('https://www.mysite.de/some/page/?type=1701', $this->subject->generatePageUrl(['uid' => 123]));
    }
}
