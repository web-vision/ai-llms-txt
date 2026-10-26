<?php

declare(strict_types=1);

namespace WebVision\AiLlmsTxt\Tests\Unit\Controller;

use PHPUnit\Framework\Attributes\Test;
use Psr\Log\NullLogger;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;
use WebVision\AiLlmsTxt\Repository\PageRepository;
use WebVision\AiLlmsTxt\Service\ConfigurationService;
use WebVision\AiLlmsTxt\Service\LlmsTxtGeneratorService;
use WebVision\AiLlmsTxt\Service\MarkdownConverterService;
use WebVision\AiLlmsTxt\Tests\Unit\Controller\Fixtures\PageHtmlAccessibleLlmsTxtController;

/**
 * Unit tests for combining page title, description and content in the Markdown version.
 *
 * A page whose content starts with its own main heading must not end up with two of them.
 */
final class LlmsTxtControllerPageHtmlTest extends UnitTestCase
{
    private PageHtmlAccessibleLlmsTxtController $subject;

    protected function setUp(): void
    {
        parent::setUp();

        $this->subject = new PageHtmlAccessibleLlmsTxtController(
            $this->createMock(LlmsTxtGeneratorService::class),
            $this->createMock(ConfigurationService::class),
            $this->createMock(MarkdownConverterService::class),
            $this->createMock(PageRepository::class),
            new NullLogger()
        );
    }

    #[Test]
    public function pageTitleAndDescriptionArePutInFrontOfContentWithoutMainHeading(): void
    {
        $result = $this->subject->runComposePageHtml('Page', 'About it', '<h2>Section</h2><p>Text</p>');

        static::assertSame(
            '<h1>Page</h1><p class="page-description"> > About it</p><h2>Section</h2><p>Text</p>',
            $result
        );
    }

    #[Test]
    public function pageTitleIsLeftOutWhenContentStartsWithMainHeading(): void
    {
        $result = $this->subject->runComposePageHtml(
            'Page',
            'About it',
            '<div class="frame"><header><h1 class="title">Content Title</h1></header><p>Text</p></div>'
        );

        static::assertSame(
            '<div class="frame"><header><h1 class="title">Content Title</h1>'
            . '<p class="page-description"> > About it</p></header><p>Text</p></div>',
            $result
        );
    }

    #[Test]
    public function pageTitleIsKeptWhenMainHeadingFollowsAnotherHeading(): void
    {
        $result = $this->subject->runComposePageHtml('Page', '', '<h2>Section</h2><h1>Later</h1>');

        static::assertSame('<h1>Page</h1><h2>Section</h2><h1>Later</h1>', $result);
    }

    #[Test]
    public function pageTitleIsKeptWhenMainHeadingIsNotClosed(): void
    {
        $result = $this->subject->runComposePageHtml('Page', '', '<h1>Unclosed');

        static::assertSame('<h1>Page</h1><h1>Unclosed', $result);
    }

    #[Test]
    public function headerElementIsNotMistakenForMainHeading(): void
    {
        $result = $this->subject->runComposePageHtml('Page', '', '<header><p>Intro</p></header><hr><h2>Section</h2>');

        static::assertStringStartsWith('<h1>Page</h1>', $result);
    }

    #[Test]
    public function mainHeadingIsDetectedCaseInsensitively(): void
    {
        $result = $this->subject->runComposePageHtml('Page', 'About it', '<H1>Content Title</H1><p>Text</p>');

        static::assertSame('<H1>Content Title</H1><p class="page-description"> > About it</p><p>Text</p>', $result);
    }

    #[Test]
    public function titleAndDescriptionAreEscaped(): void
    {
        $result = $this->subject->runComposePageHtml('<b>Page</b>', 'A & B', '');

        static::assertSame('<h1>&lt;b&gt;Page&lt;/b&gt;</h1><p class="page-description"> > A &amp; B</p>', $result);
    }
}
