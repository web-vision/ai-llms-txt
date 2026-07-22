<?php

declare(strict_types=1);

namespace WebVision\AiLlmsTxt\Tests\Functional\Service;

use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;
use WebVision\AiLlmsTxt\Service\HtmlCleanerService;

/**
 * Functional tests for HtmlCleanerService
 *
 * Tests TYPO3-specific HTML cleaning functionality
 */
final class HtmlCleanerServiceTest extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'web-vision/ai-llms-txt',
    ];

    protected array $coreExtensionsToLoad = [
        'seo',
    ];

    private HtmlCleanerService $subject;

    protected function setUp(): void
    {
        parent::setUp();

        $this->subject = $this->get(HtmlCleanerService::class);
    }

    #[Test]
    public function cleanTypo3HtmlRemovesEmptyDivs(): void
    {
        $html = '<div class="wrapper"><div></div></div><p>Content</p>';

        $result = $this->subject->cleanTypo3Html($html);

        static::assertStringNotContainsString('<div></div>', $result);
        static::assertStringContainsString('Content', $result);
    }

    #[Test]
    public function cleanTypo3HtmlRemovesEmptyParagraphs(): void
    {
        $html = '<p>Content</p><p></p><p>   </p><p>More</p>';

        $result = $this->subject->cleanTypo3Html($html);

        static::assertStringContainsString('Content', $result);
        static::assertStringContainsString('More', $result);
    }

    #[Test]
    public function cleanTypo3HtmlRemovesEmptySpans(): void
    {
        $html = '<p>Text <span></span>here</p>';

        $result = $this->subject->cleanTypo3Html($html);

        static::assertStringNotContainsString('<span></span>', $result);
    }

    #[Test]
    public function cleanTypo3HtmlRemovesEmptyLinks(): void
    {
        $html = '<p>Text <a href="#"></a>here</p>';

        $result = $this->subject->cleanTypo3Html($html);

        static::assertStringNotContainsString('<a href="#"></a>', $result);
    }

    #[Test]
    public function cleanTypo3HtmlRemovesContainerDivs(): void
    {
        $html = '<div class="container"><p>Content</p></div>';

        $result = $this->subject->cleanTypo3Html($html);

        static::assertStringNotContainsString('container', $result);
        static::assertStringContainsString('Content', $result);
    }

    #[Test]
    public function cleanTypo3HtmlRemovesRowDivs(): void
    {
        $html = '<div class="row"><p>Content</p></div>';

        $result = $this->subject->cleanTypo3Html($html);

        static::assertStringNotContainsString('row', $result);
        static::assertStringContainsString('Content', $result);
    }

    #[Test]
    public function cleanTypo3HtmlRemovesColumnDivs(): void
    {
        $html = '<div class="col-md-6"><p>Content</p></div>';

        $result = $this->subject->cleanTypo3Html($html);

        static::assertStringNotContainsString('col-md-6', $result);
        static::assertStringContainsString('Content', $result);
    }

    #[Test]
    public function cleanTypo3HtmlRemovesGridDivs(): void
    {
        $html = '<div class="grid-container"><p>Content</p></div>';

        $result = $this->subject->cleanTypo3Html($html);

        static::assertStringNotContainsString('grid', $result);
        static::assertStringContainsString('Content', $result);
    }

    #[Test]
    public function cleanTypo3HtmlRemovesLayoutDivs(): void
    {
        $html = '<div class="page-layout"><p>Content</p></div>';

        $result = $this->subject->cleanTypo3Html($html);

        static::assertStringNotContainsString('layout', $result);
        static::assertStringContainsString('Content', $result);
    }

    #[Test]
    public function cleanTypo3HtmlRemovesWrapperDivs(): void
    {
        $html = '<div class="content-wrapper"><p>Content</p></div>';

        $result = $this->subject->cleanTypo3Html($html);

        static::assertStringNotContainsString('wrapper', $result);
        static::assertStringContainsString('Content', $result);
    }

    #[Test]
    public function cleanTypo3HtmlPreservesSemanticHtml(): void
    {
        $html = '<article><h1>Title</h1><p>Content</p></article>';

        $result = $this->subject->cleanTypo3Html($html);

        static::assertStringContainsString('Title', $result);
        static::assertStringContainsString('Content', $result);
    }

    #[Test]
    public function cleanTypo3HtmlHandlesNestedEmptyElements(): void
    {
        $html = '<div><div><span></span></div></div><p>Content</p>';

        $result = $this->subject->cleanTypo3Html($html);

        static::assertStringContainsString('Content', $result);
    }

    #[Test]
    public function cleanTypo3HtmlNormalizesWhitespace(): void
    {
        $html = '<p>Text    with    extra    spaces</p>';

        $result = $this->subject->cleanTypo3Html($html);

        // Multiple spaces should be normalized
        static::assertStringNotContainsString('    ', $result);
    }

    #[Test]
    public function cleanTypo3HtmlTrimsResult(): void
    {
        $html = '   <p>Content</p>   ';

        $result = $this->subject->cleanTypo3Html($html);

        static::assertSame($result, trim($result));
    }

    #[Test]
    public function cleanTypo3HtmlHandlesComplexTypo3Output(): void
    {
        $html = '
            <div class="frame frame-default frame-type-textmedia">
                <div class="container">
                    <div class="row">
                        <div class="col-12">
                            <h2>Headline</h2>
                            <p>Paragraph text here.</p>
                            <ul>
                                <li>List item 1</li>
                                <li>List item 2</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        ';

        $result = $this->subject->cleanTypo3Html($html);

        // Content should be preserved
        static::assertStringContainsString('Headline', $result);
        static::assertStringContainsString('Paragraph text', $result);
        static::assertStringContainsString('List item 1', $result);

        // Layout classes should be stripped
        static::assertStringNotContainsString('container', $result);
        static::assertStringNotContainsString('row', $result);
        static::assertStringNotContainsString('col-12', $result);
    }

    #[Test]
    public function cleanTypo3HtmlHandlesEmptyInput(): void
    {
        $result = $this->subject->cleanTypo3Html('');

        static::assertSame('', $result);
    }

    #[Test]
    public function cleanTypo3HtmlRemovesEmptySectionAndArticle(): void
    {
        $html = '<section></section><article></article><p>Content</p>';

        $result = $this->subject->cleanTypo3Html($html);

        static::assertStringContainsString('Content', $result);
    }

    #[Test]
    public function cleanTypo3HtmlRemovesEmptyAside(): void
    {
        $html = '<aside></aside><p>Content</p>';

        $result = $this->subject->cleanTypo3Html($html);

        static::assertStringContainsString('Content', $result);
    }

    #[Test]
    public function cleanTypo3HtmlPreservesLinksWithContent(): void
    {
        $html = '<p>Visit <a href="https://example.com">our site</a></p>';

        $result = $this->subject->cleanTypo3Html($html);

        static::assertStringContainsString('our site', $result);
        static::assertStringContainsString('https://example.com', $result);
    }

    #[Test]
    public function cleanTypo3HtmlPreservesHeadings(): void
    {
        $html = '<h1>H1</h1><h2>H2</h2><h3>H3</h3><h4>H4</h4><h5>H5</h5><h6>H6</h6>';

        $result = $this->subject->cleanTypo3Html($html);

        static::assertStringContainsString('H1', $result);
        static::assertStringContainsString('H2', $result);
        static::assertStringContainsString('H3', $result);
        static::assertStringContainsString('H4', $result);
        static::assertStringContainsString('H5', $result);
        static::assertStringContainsString('H6', $result);
    }

    #[Test]
    public function cleanTypo3HtmlPreservesLists(): void
    {
        $html = '<ul><li>Item 1</li><li>Item 2</li></ul><ol><li>First</li></ol>';

        $result = $this->subject->cleanTypo3Html($html);

        static::assertStringContainsString('Item 1', $result);
        static::assertStringContainsString('First', $result);
    }
}
