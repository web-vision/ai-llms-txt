<?php

declare(strict_types=1);

namespace WebVision\AiLlmsTxt\Tests\Functional\Builder;

use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Configuration\SiteConfiguration;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;
use WebVision\AiLlmsTxt\Builder\NavigationBuilder;

/**
 * Regression tests for the multilingual deep page tree bug.
 *
 * Bug: On a multilingual site with a deep page tree, pages at treeLevel 2+ were
 * all attributed to the default language. The llms.txt output looked like:
 *
 *   ## English
 *   + Page One
 *     - Sub Page One         ← correct
 *     - Unterseite Eins      ← BUG: should be under German
 *     - Souspage Francaise   ← BUG: should be under French
 *
 *   ## German
 *   + Seite Eins
 *   (no subpages)            ← BUG: subpage missing
 *
 * Fix: NavigationBuilder::buildChildrenOptimized() now correctly passes each
 * main page's sys_language_uid to the repository query, so children are
 * fetched in the right language.
 */
final class NavigationBuilderMultilingualTest extends FunctionalTestCase
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

        $this->importCSVDataSet(__DIR__ . '/../Fixtures/pages_multilingual_deep.csv');
        $this->writeSiteConfiguration();

        $this->subject = $this->get(NavigationBuilder::class);
    }

    /**
     * Write a three-language site configuration and invalidate the TYPO3 site caches
     * so that SiteFinder and UrlGeneratorService pick up the new configuration.
     */
    private function writeSiteConfiguration(): void
    {
        $siteDir = $this->instancePath . '/typo3conf/sites/test-site';
        @mkdir($siteDir, 0777, true);
        file_put_contents(
            $siteDir . '/config.yaml',
            <<<'YAML'
                rootPageId: 1
                base: 'https://example.com/'
                languages:
                  -
                    languageId: 0
                    title: English
                    navigationTitle: English
                    base: '/'
                    locale: en_US.UTF-8
                    flag: us
                  -
                    languageId: 1
                    title: German
                    navigationTitle: German
                    base: '/de/'
                    locale: de_DE.UTF-8
                    flag: de
                    fallbackType: strict
                  -
                    languageId: 2
                    title: French
                    navigationTitle: French
                    base: '/fr/'
                    locale: fr_FR.UTF-8
                    flag: fr
                    fallbackType: strict
                YAML
        );

        // Invalidate cached site configs so SiteFinder re-reads from the filesystem
        $this->get(SiteConfiguration::class)->siteConfigurationChanged();
        $this->get(SiteFinder::class)->siteConfigurationChanged();
    }

    /**
     * Regression: when build() is called without a SiteLanguage (BC path), each
     * language's top-level pages must carry ONLY their own language's children.
     * Before the fix, all subpages from all languages appeared as children of the
     * default-language page.
     */
    #[Test]
    public function buildWithoutLanguageAttributesChildrenToCorrectLanguageSection(): void
    {
        $structure = $this->subject->build(1, 2);

        // Index sections by title for easy lookup
        $sections = [];
        foreach ($structure as $section) {
            $sections[$section['title']] = $section;
        }

        static::assertArrayHasKey('Page One', $sections, 'EN level-1 page must be present');
        static::assertArrayHasKey('Seite Eins', $sections, 'DE level-1 page must be present');
        static::assertArrayHasKey('Page Francaise', $sections, 'FR level-1 page must be present');

        // EN parent must have only its EN child
        $enChildTitles = array_column($sections['Page One']['children'], 'title');
        static::assertContains('Sub Page One', $enChildTitles, 'EN child must appear under EN parent');
        static::assertNotContains('Unterseite Eins', $enChildTitles, 'DE child must not appear under EN parent');
        static::assertNotContains('Souspage Francaise', $enChildTitles, 'FR child must not appear under EN parent');

        // DE parent must have only its DE child
        $deChildTitles = array_column($sections['Seite Eins']['children'], 'title');
        static::assertContains('Unterseite Eins', $deChildTitles, 'DE child must appear under DE parent');
        static::assertNotContains('Sub Page One', $deChildTitles, 'EN child must not appear under DE parent');
        static::assertNotContains('Souspage Francaise', $deChildTitles, 'FR child must not appear under DE parent');

        // FR parent must have only its FR child
        $frChildTitles = array_column($sections['Page Francaise']['children'], 'title');
        static::assertContains('Souspage Francaise', $frChildTitles, 'FR child must appear under FR parent');
        static::assertNotContains('Sub Page One', $frChildTitles, 'EN child must not appear under FR parent');
        static::assertNotContains('Unterseite Eins', $frChildTitles, 'DE child must not appear under FR parent');
    }

    /**
     * Regression: build() with a specific SiteLanguage must return children in that
     * language only. Before the fix, children were fetched in the default language.
     */
    #[Test]
    public function buildWithGermanLanguageReturnsOnlyGermanChildren(): void
    {
        $site = $this->get(SiteFinder::class)->getSiteByIdentifier('test-site');
        $deLanguage = $site->getLanguageById(1);

        $structure = $this->subject->build(1, 2, $deLanguage);

        $titles = array_column($structure, 'title');
        static::assertContains('Seite Eins', $titles, 'DE level-1 page must be present');
        static::assertNotContains('Page One', $titles, 'EN level-1 page must not appear in DE navigation');
        static::assertNotContains('Page Francaise', $titles, 'FR level-1 page must not appear in DE navigation');

        // Find the DE parent and verify its children
        $seiteEins = null;
        foreach ($structure as $section) {
            if ($section['title'] === 'Seite Eins') {
                $seiteEins = $section;
                break;
            }
        }
        static::assertNotNull($seiteEins, 'Seite Eins must be found in DE navigation');

        $childTitles = array_column($seiteEins['children'], 'title');
        static::assertContains('Unterseite Eins', $childTitles, 'DE child must appear under DE parent');
        static::assertNotContains('Sub Page One', $childTitles, 'EN child must not appear in DE navigation');
        static::assertNotContains('Souspage Francaise', $childTitles, 'FR child must not appear in DE navigation');
    }

    /**
     * Regression: formatAsMarkdown must place each language's children inside
     * the correct language section. Before the fix, the German and French children
     * both appeared inside the "## English" block.
     */
    #[Test]
    public function formatAsMarkdownPlacesChildrenInCorrectLanguageSection(): void
    {
        $structure = $this->subject->build(1, 2);
        $markdown = implode("\n", $this->subject->formatAsMarkdown($structure));

        // Split the rendered markdown into per-language blocks.
        // The regex captures the language title and everything until the next ## header or end.
        preg_match_all('/^## ([^\n]+)\n(.*?)(?=\n## |\z)/ms', $markdown, $matches, PREG_SET_ORDER);

        $blocks = [];
        foreach ($matches as $match) {
            $blocks[$match[1]] = $match[2];
        }

        static::assertArrayHasKey('English', $blocks, '## English section must exist');
        static::assertArrayHasKey('German', $blocks, '## German section must exist');
        static::assertArrayHasKey('French', $blocks, '## French section must exist');

        // Each child page title must appear in its own language section only
        static::assertStringContainsString('Sub Page One', $blocks['English']);
        static::assertStringNotContainsString('Unterseite Eins', $blocks['English'], 'DE child must not be in English section');
        static::assertStringNotContainsString('Souspage Francaise', $blocks['English'], 'FR child must not be in English section');

        static::assertStringContainsString('Unterseite Eins', $blocks['German']);
        static::assertStringNotContainsString('Sub Page One', $blocks['German'], 'EN child must not be in German section');

        static::assertStringContainsString('Souspage Francaise', $blocks['French']);
        static::assertStringNotContainsString('Sub Page One', $blocks['French'], 'EN child must not be in French section');
    }
}
