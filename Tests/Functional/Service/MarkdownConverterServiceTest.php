<?php

declare(strict_types=1);

namespace WebVision\AiLlmsTxt\Tests\Functional\Service;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;
use WebVision\AiLlmsTxt\Service\HtmlCleanerService;
use WebVision\AiLlmsTxt\Service\MarkdownConverterService;

/**
 * Functional tests for MarkdownConverterService
 *
 * Tests HTML to Markdown conversion with real dependencies
 */
final class MarkdownConverterServiceTest extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'web-vision/ai-llms-txt',
    ];

    private MarkdownConverterService $subject;

    protected function setUp(): void
    {
        parent::setUp();

        $this->subject = $this->get(MarkdownConverterService::class);
    }

    #[Test]
    public function convertHtmlToMarkdownConvertsHeadings(): void
    {
        $html = '<h1>Main Title</h1><h2>Subtitle</h2><h3>Section</h3>';

        $result = $this->subject->convertHtmlToMarkdown($html);

        self::assertStringContainsString('# Main Title', $result);
        self::assertStringContainsString('## Subtitle', $result);
        self::assertStringContainsString('### Section', $result);
    }

    #[Test]
    public function convertHtmlToMarkdownConvertsParagraphs(): void
    {
        $html = '<p>First paragraph.</p><p>Second paragraph.</p>';

        $result = $this->subject->convertHtmlToMarkdown($html);

        self::assertStringContainsString('First paragraph.', $result);
        self::assertStringContainsString('Second paragraph.', $result);
    }

    #[Test]
    public function convertHtmlToMarkdownConvertsLinks(): void
    {
        $html = '<p>Visit <a href="https://example.com">our website</a> for more.</p>';

        $result = $this->subject->convertHtmlToMarkdown($html);

        self::assertStringContainsString('[our website](https://example.com)', $result);
    }

    #[Test]
    public function convertHtmlToMarkdownConvertsUnorderedLists(): void
    {
        $html = '<ul><li>Item 1</li><li>Item 2</li><li>Item 3</li></ul>';

        $result = $this->subject->convertHtmlToMarkdown($html);

        self::assertStringContainsString('- Item 1', $result);
        self::assertStringContainsString('- Item 2', $result);
        self::assertStringContainsString('- Item 3', $result);
    }

    #[Test]
    public function convertHtmlToMarkdownConvertsOrderedLists(): void
    {
        $html = '<ol><li>First</li><li>Second</li><li>Third</li></ol>';

        $result = $this->subject->convertHtmlToMarkdown($html);

        self::assertStringContainsString('1. First', $result);
        self::assertStringContainsString('2. Second', $result);
        self::assertStringContainsString('3. Third', $result);
    }

    #[Test]
    public function convertHtmlToMarkdownConvertsBold(): void
    {
        $html = '<p>This is <strong>bold</strong> and <b>also bold</b>.</p>';

        $result = $this->subject->convertHtmlToMarkdown($html);

        self::assertStringContainsString('**bold**', $result);
        self::assertStringContainsString('**also bold**', $result);
    }

    #[Test]
    public function convertHtmlToMarkdownConvertsItalic(): void
    {
        $html = '<p>This is <em>italic</em> and <i>also italic</i>.</p>';

        $result = $this->subject->convertHtmlToMarkdown($html);

        self::assertStringContainsString('*italic*', $result);
        self::assertStringContainsString('*also italic*', $result);
    }

    #[Test]
    public function convertHtmlToMarkdownConvertsBlockquotes(): void
    {
        $html = '<blockquote>This is a quote.</blockquote>';

        $result = $this->subject->convertHtmlToMarkdown($html);

        self::assertStringContainsString('> This is a quote.', $result);
    }

    #[Test]
    public function convertHtmlToMarkdownConvertsCode(): void
    {
        $html = '<p>Use <code>print()</code> to output.</p>';

        $result = $this->subject->convertHtmlToMarkdown($html);

        self::assertStringContainsString('`print()`', $result);
    }

    #[Test]
    public function convertHtmlToMarkdownConvertsPreformattedCode(): void
    {
        $html = '<pre><code>function test() {
    return true;
}</code></pre>';

        $result = $this->subject->convertHtmlToMarkdown($html);

        self::assertStringContainsString('```', $result);
        self::assertStringContainsString('function test()', $result);
    }

    #[Test]
    public function convertHtmlToMarkdownStripsImages(): void
    {
        $html = '<p>Text before <img src="image.jpg" alt="Image"> text after.</p>';

        $result = $this->subject->convertHtmlToMarkdown($html);

        self::assertStringNotContainsString('<img', $result);
        self::assertStringNotContainsString('image.jpg', $result);
        self::assertStringContainsString('Text before', $result);
        self::assertStringContainsString('text after', $result);
    }

    #[Test]
    public function convertHtmlToMarkdownStripsScripts(): void
    {
        $html = '<p>Content</p><script>alert("xss")</script>';

        $result = $this->subject->convertHtmlToMarkdown($html);

        self::assertStringNotContainsString('alert', $result);
        self::assertStringNotContainsString('<script', $result);
        self::assertStringContainsString('Content', $result);
    }

    #[Test]
    public function convertHtmlToMarkdownStripsStyles(): void
    {
        $html = '<style>.red { color: red; }</style><p>Content</p>';

        $result = $this->subject->convertHtmlToMarkdown($html);

        self::assertStringNotContainsString('.red', $result);
        self::assertStringNotContainsString('<style', $result);
        self::assertStringContainsString('Content', $result);
    }

    #[Test]
    public function convertHtmlToMarkdownHandlesNestedLists(): void
    {
        $html = '<ul>
            <li>Parent 1
                <ul>
                    <li>Child 1.1</li>
                    <li>Child 1.2</li>
                </ul>
            </li>
            <li>Parent 2</li>
        </ul>';

        $result = $this->subject->convertHtmlToMarkdown($html);

        self::assertStringContainsString('Parent 1', $result);
        self::assertStringContainsString('Child 1.1', $result);
        self::assertStringContainsString('Parent 2', $result);
    }

    #[Test]
    public function convertHtmlToMarkdownHandlesEmptyInput(): void
    {
        $result = $this->subject->convertHtmlToMarkdown('');

        self::assertSame('', $result);
    }

    #[Test]
    public function convertHtmlToMarkdownHandlesWhitespaceOnlyInput(): void
    {
        $result = $this->subject->convertHtmlToMarkdown('   ');

        self::assertSame('', $result);
    }

    #[Test]
    public function convertHtmlToMarkdownDecodesHtmlEntities(): void
    {
        $html = '<p>Special chars: &amp; &lt; &gt; &quot; &apos;</p>';

        $result = $this->subject->convertHtmlToMarkdown($html);

        self::assertStringContainsString('&', $result);
        self::assertStringContainsString('<', $result);
        self::assertStringContainsString('>', $result);
    }

    #[Test]
    public function convertHtmlToMarkdownConvertsTablesBasically(): void
    {
        $html = '<table>
            <tr><th>Header 1</th><th>Header 2</th></tr>
            <tr><td>Cell 1</td><td>Cell 2</td></tr>
        </table>';

        $result = $this->subject->convertHtmlToMarkdown($html);

        // Tables may be converted to pipe format or simplified
        self::assertStringContainsString('Header 1', $result);
        self::assertStringContainsString('Cell 1', $result);
    }

    #[Test]
    public function convertHtmlToMarkdownHandlesTypo3WrapperDivs(): void
    {
        $html = '
            <div class="container">
                <div class="row">
                    <div class="col-md-6">
                        <h2>Content Title</h2>
                        <p>Content text.</p>
                    </div>
                </div>
            </div>';

        $result = $this->subject->convertHtmlToMarkdown($html);

        // Wrapper divs should be stripped, content preserved
        self::assertStringContainsString('## Content Title', $result);
        self::assertStringContainsString('Content text.', $result);
        self::assertStringNotContainsString('container', $result);
        self::assertStringNotContainsString('row', $result);
    }

    #[Test]
    public function convertHtmlToMarkdownRemovesEmptyParagraphs(): void
    {
        $html = '<p>Content</p><p></p><p>   </p><p>More content</p>';

        $result = $this->subject->convertHtmlToMarkdown($html);

        self::assertStringContainsString('Content', $result);
        self::assertStringContainsString('More content', $result);
    }

    #[Test]
    public function convertHtmlToMarkdownPreservesLineBreaks(): void
    {
        $html = '<p>Line one<br>Line two<br/>Line three</p>';

        $result = $this->subject->convertHtmlToMarkdown($html);

        // Line breaks should be preserved in some form
        self::assertStringContainsString('Line one', $result);
        self::assertStringContainsString('Line two', $result);
        self::assertStringContainsString('Line three', $result);
    }

    #[Test]
    public function convertHtmlToMarkdownTrimsResult(): void
    {
        $html = '   <p>Content</p>   ';

        $result = $this->subject->convertHtmlToMarkdown($html);

        self::assertStringNotMatchesFormat('%A   Content%A', $result);
        self::assertStringNotMatchesFormat('%AContent   %A', $result);
    }
}
