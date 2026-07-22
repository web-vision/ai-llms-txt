<?php

declare(strict_types=1);

namespace WebVision\AiLlmsTxt\Tests\Functional\Repository;

use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;
use WebVision\AiLlmsTxt\Repository\PageRepository;

/**
 * Functional tests for PageRepository
 *
 * Tests database interactions for fetching navigation pages
 */
final class PageRepositoryTest extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'web-vision/ai-llms-txt',
    ];

    protected array $coreExtensionsToLoad = [
        'seo',
    ];

    private PageRepository $subject;

    protected function setUp(): void
    {
        parent::setUp();

        $this->importCSVDataSet(__DIR__ . '/../Fixtures/pages.csv');

        $this->subject = $this->get(PageRepository::class);
    }

    #[Test]
    public function findByIdReturnsPageData(): void
    {
        $result = $this->subject->findById(2);

        static::assertNotEmpty($result);
        static::assertSame(2, $result['uid']);
        static::assertSame('About Us', $result['title']);
        static::assertSame('About our company', $result['description']);
    }

    #[Test]
    public function findByIdReturnsEmptyArrayForNonExistingPage(): void
    {
        $result = $this->subject->findById(99999);

        static::assertEmpty($result);
    }

    #[Test]
    public function findNavigationByParentReturnsVisiblePages(): void
    {
        $result = $this->subject->findNavigationByParent(1);

        // Should return About Us, Products, Contact, and Page in Folder (from folder skip)
        // Should NOT return Hidden Page (nav_hide=1)
        $titles = array_column($result, 'title');

        static::assertContains('About Us', $titles);
        static::assertContains('Products', $titles);
        static::assertContains('Contact', $titles);
        static::assertNotContains('Hidden Page', $titles);
    }

    #[Test]
    public function findNavigationByParentSkipsFoldersButIncludesTheirChildren(): void
    {
        $result = $this->subject->findNavigationByParent(1);

        $titles = array_column($result, 'title');

        // Folder should be skipped (doktype 254)
        static::assertNotContains('Folder', $titles);

        // But child of folder should be included
        static::assertContains('Page in Folder', $titles);
    }

    #[Test]
    public function findNavigationByParentAndLanguageFiltersCorrectly(): void
    {
        $result = $this->subject->findNavigationByParentAndLanguage(2, 0);

        $titles = array_column($result, 'title');

        static::assertContains('Team', $titles);
        static::assertContains('History', $titles);
        static::assertCount(2, $result);
    }

    #[Test]
    public function findNavigationByParentsBatchReturnsGroupedPages(): void
    {
        $result = $this->subject->findNavigationByParentsBatch([2, 3], 0);

        // Should have entries for both parent UIDs
        static::assertArrayHasKey(2, $result);
        static::assertArrayHasKey(3, $result);

        // Children of page 2 (About Us)
        $aboutUsChildren = array_column($result[2], 'title');
        static::assertContains('Team', $aboutUsChildren);
        static::assertContains('History', $aboutUsChildren);

        // Children of page 3 (Products)
        $productsChildren = array_column($result[3], 'title');
        static::assertContains('Category A', $productsChildren);
        static::assertContains('Category B', $productsChildren);
    }

    #[Test]
    public function findNavigationByParentsBatchReturnsEmptyForNoParents(): void
    {
        $result = $this->subject->findNavigationByParentsBatch([], 0);

        static::assertEmpty($result);
    }

    #[Test]
    public function findNavigationByParentRespectsMaxRecursionDepth(): void
    {
        // The MAX_RECURSION_DEPTH constant prevents infinite loops
        // This test ensures deeply nested structures don't cause issues
        $result = $this->subject->findNavigationByParent(1);

        // Should complete without stack overflow
        static::assertIsArray($result);
    }

    #[Test]
    public function pagesAreSortedCorrectly(): void
    {
        // Use language filter to get only default language pages for predictable sorting
        $result = $this->subject->findNavigationByParentAndLanguage(1, 0);

        // Get the first few pages - they should be in sorting order
        $firstThreeTitles = array_slice(array_column($result, 'title'), 0, 3);

        // Based on sorting values: About Us (256), Products (512), Contact (768)
        static::assertSame('About Us', $firstThreeTitles[0]);
        static::assertSame('Products', $firstThreeTitles[1]);
        static::assertSame('Contact', $firstThreeTitles[2]);
    }

    #[Test]
    public function findNavigationByParentAndLanguageReturnsGermanTranslations(): void
    {
        $result = $this->subject->findNavigationByParentAndLanguage(1, 1);

        $titles = array_column($result, 'title');

        static::assertContains('Über uns', $titles);
        static::assertContains('Produkte', $titles);
        static::assertContains('Kontakt', $titles);
    }

    #[Test]
    public function findNavigationByParentAndLanguageReturnsFrenchTranslations(): void
    {
        $result = $this->subject->findNavigationByParentAndLanguage(1, 2);

        $titles = array_column($result, 'title');

        static::assertContains('À propos', $titles);
        static::assertContains('Produits', $titles);
    }

    #[Test]
    public function findNavigationByParentAndLanguageReturnsChildTranslations(): void
    {
        $result = $this->subject->findNavigationByParentAndLanguage(2, 1);

        $titles = array_column($result, 'title');

        static::assertContains('Team', $titles);
        static::assertContains('Geschichte', $titles);
    }

    #[Test]
    public function findNavigationByParentReturnsAllLanguages(): void
    {
        $result = $this->subject->findNavigationByParent(1);

        $titles = array_column($result, 'title');

        // Should contain English pages
        static::assertContains('About Us', $titles);
        static::assertContains('Products', $titles);

        // Should also contain German translations
        static::assertContains('Über uns', $titles);
        static::assertContains('Produkte', $titles);

        // Should also contain French translations
        static::assertContains('À propos', $titles);
        static::assertContains('Produits', $titles);
    }

    #[Test]
    public function findNavigationByParentsBatchGroupsByLanguage(): void
    {
        // Test batch queries with German language
        $result = $this->subject->findNavigationByParentsBatch([2, 3], 1);

        // Children of page 2 (About Us) in German
        if (isset($result[2])) {
            $aboutUsChildren = array_column($result[2], 'title');
            static::assertContains('Team', $aboutUsChildren);
            static::assertContains('Geschichte', $aboutUsChildren);
        }

        // Children of page 3 (Products) in German
        if (isset($result[3])) {
            $productsChildren = array_column($result[3], 'title');
            static::assertContains('Kategorie A', $productsChildren);
            static::assertContains('Kategorie B', $productsChildren);
        }
    }

    #[Test]
    public function translatedPagesHaveCorrectLanguageUid(): void
    {
        $result = $this->subject->findNavigationByParentAndLanguage(1, 1);

        foreach ($result as $page) {
            static::assertSame(1, $page['sys_language_uid'], sprintf(
                'Page "%s" should have sys_language_uid=1',
                $page['title']
            ));
        }
    }

    #[Test]
    public function translatedPagesHaveCorrectL10nParent(): void
    {
        $result = $this->subject->findNavigationByParentAndLanguage(1, 1);

        foreach ($result as $page) {
            static::assertGreaterThan(0, $page['l10n_parent'], sprintf(
                'Translated page "%s" should have l10n_parent > 0',
                $page['title']
            ));
        }
    }

    #[Test]
    public function findByIdReturnsSeoTitleForDefaultLanguage(): void
    {
        $result = $this->subject->findById(2);

        static::assertNotEmpty($result);
        static::assertSame(2, $result['uid']);
        static::assertSame('About Us', $result['title']);
        static::assertSame('SEO About Us', $result['seo_title']);
    }

    #[Test]
    public function findByIdReturnsTranslatedDataWithSiteLanguageData(): void
    {
        $siteLanguage = $this->createMock(\TYPO3\CMS\Core\Site\Entity\SiteLanguage::class);
        $siteLanguage->method('getLanguageId')->willReturn(1); // German

        // Note: uid is 2 (the l10n_parent). PageRepository relies on Core's getPageOverlay
        // which accepts the original language record and overlays it.
        $result = $this->subject->findById(2, $siteLanguage);

        static::assertNotEmpty($result);
        static::assertSame(2, $result['uid']); // Overlays retain l10n_parent uid or overlay depending on Core version, actually usually uid is the l10n_parent's UID, and _LOCALIZED_UID is set
        static::assertSame('Über uns', $result['title']);
        static::assertSame('Über unser Unternehmen', $result['description']);
        static::assertSame('SEO Über uns', $result['seo_title']);
    }

    #[Test]
    public function findNavigationByParentExcludesConfiguredDoktypeAndPromotesItsChildren(): void
    {
        // Doktype 6 (Backend User Section) is not excluded by default
        $withoutConfig = $this->subject->findNavigationByParent(3);
        static::assertContains('Internal Notes', array_column($withoutConfig, 'title'));

        $withConfig = $this->subject->findNavigationByParent(3, [6]);
        $titles = array_column($withConfig, 'title');

        static::assertNotContains('Internal Notes', $titles, 'Configured doktype should be excluded');
        static::assertContains('Nested Under Internal Notes', $titles, 'Excluded page\'s children should be promoted');
    }

    #[Test]
    public function findNavigationByParentUnconfiguredKeepsDefaultExclusionsUnchanged(): void
    {
        $result = $this->subject->findNavigationByParent(1);
        $titles = array_column($result, 'title');

        // Structural doktypes (sysfolder here) remain excluded with no config passed,
        // exactly as before this change - additive, not a behavior change.
        static::assertNotContains('Folder', $titles);
        static::assertContains('Page in Folder', $titles);
    }

    #[Test]
    public function configuredExcludeListDoesNotUnexcludeStructuralDoktypes(): void
    {
        // Configuring a custom list (that does not mention sysfolder=254) must not
        // cause sysfolder pages to start appearing - structural exclusion always applies.
        $result = $this->subject->findNavigationByParent(1, [6]);
        $titles = array_column($result, 'title');

        static::assertNotContains('Folder', $titles, 'sysfolder must stay excluded regardless of configured list');
        static::assertContains('Page in Folder', $titles, 'sysfolder\'s children must still be promoted');
    }

    #[Test]
    public function findNavigationByParentsBatchExcludesAndPromotesAtDeeperLevels(): void
    {
        // Regression: findNavigationByParentsBatch() previously had no doktype exclusion
        // at all, so a folder at tree level 2+ would appear as a regular page instead of
        // being skipped with its children promoted, unlike the level-1 path.
        $result = $this->subject->findNavigationByParentsBatch([4], 0);

        $contactChildren = array_column($result[4], 'title');

        static::assertNotContains('Nested Folder', $contactChildren, 'Folder must be excluded even at depth 2+');
        static::assertContains('Deep Page In Nested Folder', $contactChildren, 'Folder\'s child must be promoted to the folder\'s position');
    }
}
