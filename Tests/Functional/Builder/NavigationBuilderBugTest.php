<?php

declare(strict_types=1);

namespace WebVision\AiLlmsTxt\Tests\Functional\Builder;

use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Site\Entity\SiteLanguage;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;
use WebVision\AiLlmsTxt\Builder\NavigationBuilder;
use WebVision\AiLlmsTxt\Repository\PageRepository;
use WebVision\AiLlmsTxt\Service\ConfigurationService;
use WebVision\AiLlmsTxt\Service\UrlGeneratorService;

/**
 * Functional tests for NavigationBuilder bug
 *
 * Tests that pages on tree level 2+ are correctly separated by language
 * in the generated tree structure and markdown.
 */
final class NavigationBuilderBugTest extends FunctionalTestCase
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

        $this->importCSVDataSet(__DIR__ . '/../Fixtures/pages.csv');

        $siteLanguage0 = $this->createMock(SiteLanguage::class);
        $siteLanguage0->method('getTitle')->willReturn('Default language');
        $siteLanguage0->method('getLanguageId')->willReturn(0);

        $siteLanguage1 = $this->createMock(SiteLanguage::class);
        $siteLanguage1->method('getTitle')->willReturn('Language 2');
        $siteLanguage1->method('getLanguageId')->willReturn(1);

        $siteLanguage2 = $this->createMock(SiteLanguage::class);
        $siteLanguage2->method('getTitle')->willReturn('Language 3');
        $siteLanguage2->method('getLanguageId')->willReturn(2);

        $site = $this->createMock(Site::class);
        $site->method('getLanguageById')->willReturnMap([
            [0, $siteLanguage0],
            [1, $siteLanguage1],
            [2, $siteLanguage2],
        ]);

        $siteFinder = $this->createMock(SiteFinder::class);
        $siteFinder->method('getSiteByPageId')->willReturn($site);

        $urlGenerator = $this->createMock(UrlGeneratorService::class);
        $urlGenerator->method('generatePageUrl')->willReturnCallback(function ($page) {
            $slug = $page['slug'] ?? 'page-' . $page['uid'];
            $lang = (int)($page['sys_language_uid'] ?? 0);
            $prefix = '';
            if ($lang === 1) {
                $prefix = '/de';
            }
            if ($lang === 2) {
                $prefix = '/fr';
            }
            return 'https://example.com' . $prefix . '/' . ltrim($slug, '/');
        });

        $pageRepository = $this->get(PageRepository::class);

        $configurationService = $this->createMock(ConfigurationService::class);
        $configurationService->method('getExcludeDoktypes')->willReturn([]);

        $this->subject = new NavigationBuilder($siteFinder, $pageRepository, $urlGenerator, $configurationService);
    }

    #[Test]
    public function buildSeparatesDeepPagesByLanguage(): void
    {
        // Fetch up to depth 3
        $structure = $this->subject->build(1, 3);

        $markdownLines = $this->subject->formatAsMarkdown($structure);
        $markdown = implode("\n", $markdownLines);

        // The exact bug described was that under Default Language, we saw Subpages of OTHER languages
        // e.g., "Subpage 1.1 (language 2)". If the bug is fixed, we shouldn't see that.

        // Assert Default language ONLY contains Default Language subpages
        // Check "About Us" and its children
        static::assertStringContainsString('## Default language', $markdown);
        static::assertStringContainsString('[About Us]', $markdown);
        static::assertStringContainsString('[Team](https://example.com/page-5)', $markdown);

        // Check "Products" and its children
        static::assertStringContainsString('[Products]', $markdown);
        static::assertStringContainsString('[Category A]', $markdown);
        static::assertStringContainsString('[Sub Product]', $markdown);

        // Language 2 structure (German, sys_language_uid 1)
        static::assertStringContainsString('## Language 2', $markdown);
        static::assertStringContainsString('[Über uns]', $markdown);
        // Ensure "Team" under German points to the German translation
        static::assertStringContainsString('[Team](https://example.com/de/page-104)', $markdown);

        // Ensure English subpages are NOT listed under DE
        // This is exactly the bug that was fixed: English and other language pages were showing up everywhere

        // For the French page, ensure it is present
        static::assertStringContainsString('## Language 3', $markdown);
        static::assertStringContainsString('[À propos]', $markdown);
    }

    #[Test]
    public function defaultLanguageDoesNotContainOtherLanguageDeepPages(): void
    {
        $structure = $this->subject->build(1, 3);

        // Find Default language section
        $defaultSection = array_filter($structure, fn ($node) => $node['language'] === 'Default language' && $node['title'] === 'About Us');
        $defaultSection = array_values($defaultSection)[0] ?? [];

        static::assertNotEmpty($defaultSection);
        static::assertCount(2, $defaultSection['children'], 'Should have 2 children: Team, History');

        foreach ($defaultSection['children'] as $child) {
            static::assertSame('Default language', $child['language'], 'Child of default language must be in default language.');
            static::assertStringNotContainsString('/de/', $child['url'], 'Should not contain German URLs');
            static::assertStringNotContainsString('/fr/', $child['url'], 'Should not contain French URLs');
        }
    }

    #[Test]
    public function translatedLanguageContainsOnlyTranslatedDeepPages(): void
    {
        $structure = $this->subject->build(1, 3);

        // Find Language 2 section (Über uns - German)
        $deSection = array_filter($structure, fn ($node) => $node['language'] === 'Language 2' && $node['title'] === 'Über uns');
        $deSection = array_values($deSection)[0] ?? [];

        static::assertNotEmpty($deSection);
        static::assertCount(2, $deSection['children'], 'Should have 2 children: Team, Geschichte (Translations of the default children)');

        foreach ($deSection['children'] as $child) {
            static::assertSame('Language 2', $child['language'], 'Child of Language 2 must be in Language 2.');
            // Expect URLs for German pages, assuming the slug indicates translation
            static::assertStringContainsString('/de/', $child['url'], 'Should contain German URLs for translated pages');
        }
    }
}
