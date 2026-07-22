<?php

declare(strict_types=1);

namespace WebVision\AiLlmsTxt\Tests\Functional\Builder;

use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;
use WebVision\AiLlmsTxt\Builder\NavigationBuilder;

/**
 * Functional tests for NavigationBuilder
 *
 * Tests the navigation structure formatting (markdown output)
 * Note: Tests that require site configuration for URL generation are skipped
 * as they need more complex setup with SiteBasedTestTrait
 */
final class NavigationBuilderTest extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'web-vision/ai-llms-txt',
    ];
    protected array $coreExtensionsToLoad = [
        'seo',
    ];
    private NavigationBuilder $subject;

    protected function setUp(): void
    {
        parent::setUp();

        $this->subject = $this->get(NavigationBuilder::class);
    }

    #[Test]
    public function formatAsMarkdownReturnsValidMarkdownForSimpleStructure(): void
    {
        // Create a test structure without needing database/site
        $structure = [
            [
                'title' => 'About Us',
                'description' => 'Learn about our company',
                'url' => 'https://example.com/about-us.md',
                'children' => [
                    [
                        'uid' => 5,
                        'title' => 'Team',
                        'url' => 'https://example.com/about-us/team.md',
                        'description' => 'Our team',
                        'children' => [],
                    ],
                ],
                'language' => 'EN',
            ],
        ];

        $result = $this->subject->formatAsMarkdown($structure);

        static::assertIsArray($result);
        static::assertNotEmpty($result);

        $markdown = implode("\n", $result);
        static::assertStringContainsString('## EN', $markdown);
        static::assertStringContainsString('[About Us]', $markdown);
        static::assertStringContainsString('[Team]', $markdown);
    }

    #[Test]
    public function formatAsMarkdownIncludesChildrenWithIndentation(): void
    {
        $structure = [
            [
                'title' => 'Products',
                'description' => '',
                'url' => 'https://example.com/products.md',
                'children' => [
                    [
                        'uid' => 7,
                        'title' => 'Category A',
                        'url' => 'https://example.com/products/category-a.md',
                        'description' => 'Products in category A',
                        'children' => [],
                    ],
                ],
                'language' => 'EN',
            ],
        ];

        $result = $this->subject->formatAsMarkdown($structure);
        $markdown = implode("\n", $result);

        // Children should have indentation (2 spaces)
        static::assertMatchesRegularExpression('/^  - \[/m', $markdown);
    }

    #[Test]
    public function formatAsMarkdownIncludesDescriptions(): void
    {
        $structure = [
            [
                'title' => 'About',
                'description' => '',
                'url' => 'https://example.com/about.md',
                'children' => [
                    [
                        'uid' => 5,
                        'title' => 'Team',
                        'url' => 'https://example.com/team.md',
                        'description' => 'Meet our team',
                        'children' => [],
                    ],
                ],
                'language' => 'EN',
            ],
        ];

        $result = $this->subject->formatAsMarkdown($structure);
        $markdown = implode("\n", $result);

        // Should include description after the link
        static::assertStringContainsString('Meet our team', $markdown);
    }

    #[Test]
    public function formatAsMarkdownEscapesSpecialCharacters(): void
    {
        // Create a structure with special characters
        $structure = [
            [
                'title' => 'Page [with] brackets',
                'description' => 'Description (with) parentheses',
                'url' => 'https://example.com/page',
                'children' => [],
                'language' => 'EN',
            ],
        ];

        $result = $this->subject->formatAsMarkdown($structure);
        $markdown = implode("\n", $result);

        // Brackets should be escaped with backslash in title
        static::assertStringContainsString('\\[', $markdown);
        static::assertStringContainsString('\\]', $markdown);
    }

    #[Test]
    public function formatAsMarkdownGroupsByLanguage(): void
    {
        $structure = [
            [
                'title' => 'English Page',
                'description' => '',
                'url' => 'https://example.com/en/page.md',
                'children' => [],
                'language' => 'EN',
            ],
            [
                'title' => 'German Page',
                'description' => '',
                'url' => 'https://example.com/de/page.md',
                'children' => [],
                'language' => 'DE',
            ],
        ];

        $result = $this->subject->formatAsMarkdown($structure);
        $markdown = implode("\n", $result);

        static::assertStringContainsString('## EN', $markdown);
        static::assertStringContainsString('## DE', $markdown);
    }

    #[Test]
    public function formatAsMarkdownHandlesNestedChildren(): void
    {
        $structure = [
            [
                'title' => 'Products',
                'description' => '',
                'url' => 'https://example.com/products.md',
                'children' => [
                    [
                        'uid' => 7,
                        'title' => 'Category A',
                        'url' => 'https://example.com/category-a.md',
                        'description' => '',
                        'children' => [
                            [
                                'uid' => 13,
                                'title' => 'Sub Product',
                                'url' => 'https://example.com/sub-product.md',
                                'description' => 'A sub product',
                                'children' => [],
                            ],
                        ],
                    ],
                ],
                'language' => 'EN',
            ],
        ];

        $result = $this->subject->formatAsMarkdown($structure);
        $markdown = implode("\n", $result);

        static::assertStringContainsString('Category A', $markdown);
        static::assertStringContainsString('Sub Product', $markdown);
        // Check for deeper indentation (4 spaces for level 2)
        static::assertMatchesRegularExpression('/^    - \[/m', $markdown);
    }

    #[Test]
    public function formatAsMarkdownHandlesEmptyChildren(): void
    {
        $structure = [
            [
                'title' => 'Page without children',
                'description' => '',
                'url' => 'https://example.com/page.md',
                'children' => [],
                'language' => 'EN',
            ],
        ];

        $result = $this->subject->formatAsMarkdown($structure);
        $markdown = implode("\n", $result);

        static::assertStringContainsString('[Page without children]', $markdown);
    }

    #[Test]
    public function formatAsMarkdownHandlesEmptyStructure(): void
    {
        $result = $this->subject->formatAsMarkdown([]);

        static::assertIsArray($result);
        static::assertEmpty($result);
    }
}
