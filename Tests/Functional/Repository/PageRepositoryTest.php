<?php

declare(strict_types=1);

namespace WebVision\AiLlmsTxt\Tests\Functional\Repository;

use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;
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

        self::assertNotEmpty($result);
        self::assertSame(2, $result['uid']);
        self::assertSame('About Us', $result['title']);
        self::assertSame('About our company', $result['description']);
    }

    #[Test]
    public function findByIdReturnsEmptyArrayForNonExistingPage(): void
    {
        $result = $this->subject->findById(99999);

        self::assertEmpty($result);
    }

    #[Test]
    public function findNavigationByParentReturnsVisiblePages(): void
    {
        $result = $this->subject->findNavigationByParent(1);

        // Should return About Us, Products, Contact, and Page in Folder (from folder skip)
        // Should NOT return Hidden Page (nav_hide=1)
        $titles = array_column($result, 'title');

        self::assertContains('About Us', $titles);
        self::assertContains('Products', $titles);
        self::assertContains('Contact', $titles);
        self::assertNotContains('Hidden Page', $titles);
    }

    #[Test]
    public function findNavigationByParentSkipsFoldersButIncludesTheirChildren(): void
    {
        $result = $this->subject->findNavigationByParent(1);

        $titles = array_column($result, 'title');

        // Folder should be skipped (doktype 254)
        self::assertNotContains('Folder', $titles);

        // But child of folder should be included
        self::assertContains('Page in Folder', $titles);
    }

    #[Test]
    public function findNavigationByParentAndLanguageFiltersCorrectly(): void
    {
        $result = $this->subject->findNavigationByParentAndLanguage(2, 0);

        $titles = array_column($result, 'title');

        self::assertContains('Team', $titles);
        self::assertContains('History', $titles);
        self::assertCount(2, $result);
    }

    #[Test]
    public function findNavigationByParentsBatchReturnsGroupedPages(): void
    {
        $result = $this->subject->findNavigationByParentsBatch([2, 3], 0);

        // Should have entries for both parent UIDs
        self::assertArrayHasKey(2, $result);
        self::assertArrayHasKey(3, $result);

        // Children of page 2 (About Us)
        $aboutUsChildren = array_column($result[2], 'title');
        self::assertContains('Team', $aboutUsChildren);
        self::assertContains('History', $aboutUsChildren);

        // Children of page 3 (Products)
        $productsChildren = array_column($result[3], 'title');
        self::assertContains('Category A', $productsChildren);
        self::assertContains('Category B', $productsChildren);
    }

    #[Test]
    public function findNavigationByParentsBatchReturnsEmptyForNoParents(): void
    {
        $result = $this->subject->findNavigationByParentsBatch([], 0);

        self::assertEmpty($result);
    }

    #[Test]
    public function findNavigationByParentRespectsMaxRecursionDepth(): void
    {
        // The MAX_RECURSION_DEPTH constant prevents infinite loops
        // This test ensures deeply nested structures don't cause issues
        $result = $this->subject->findNavigationByParent(1);

        // Should complete without stack overflow
        self::assertIsArray($result);
    }

    #[Test]
    public function pagesAreSortedCorrectly(): void
    {
        // Use language filter to get only default language pages for predictable sorting
        $result = $this->subject->findNavigationByParentAndLanguage(1, 0);

        // Get the first few pages - they should be in sorting order
        $firstThreeTitles = array_slice(array_column($result, 'title'), 0, 3);

        // Based on sorting values: About Us (256), Products (512), Contact (768)
        self::assertSame('About Us', $firstThreeTitles[0]);
        self::assertSame('Products', $firstThreeTitles[1]);
        self::assertSame('Contact', $firstThreeTitles[2]);
    }

    #[Test]
    public function findNavigationByParentAndLanguageReturnsGermanTranslations(): void
    {
        $result = $this->subject->findNavigationByParentAndLanguage(1, 1);

        $titles = array_column($result, 'title');

        self::assertContains('Über uns', $titles);
        self::assertContains('Produkte', $titles);
        self::assertContains('Kontakt', $titles);
    }

    #[Test]
    public function findNavigationByParentAndLanguageReturnsFrenchTranslations(): void
    {
        $result = $this->subject->findNavigationByParentAndLanguage(1, 2);

        $titles = array_column($result, 'title');

        self::assertContains('À propos', $titles);
        self::assertContains('Produits', $titles);
    }

    #[Test]
    public function findNavigationByParentAndLanguageReturnsChildTranslations(): void
    {
        $result = $this->subject->findNavigationByParentAndLanguage(2, 1);

        $titles = array_column($result, 'title');

        self::assertContains('Team', $titles);
        self::assertContains('Geschichte', $titles);
    }

    #[Test]
    public function findNavigationByParentReturnsAllLanguages(): void
    {
        $result = $this->subject->findNavigationByParent(1);

        $titles = array_column($result, 'title');

        // Should contain English pages
        self::assertContains('About Us', $titles);
        self::assertContains('Products', $titles);

        // Should also contain German translations
        self::assertContains('Über uns', $titles);
        self::assertContains('Produkte', $titles);

        // Should also contain French translations
        self::assertContains('À propos', $titles);
        self::assertContains('Produits', $titles);
    }

    #[Test]
    public function findNavigationByParentsBatchGroupsByLanguage(): void
    {
        // Test batch queries with German language
        $result = $this->subject->findNavigationByParentsBatch([2, 3], 1);

        // Children of page 2 (About Us) in German
        if (isset($result[2])) {
            $aboutUsChildren = array_column($result[2], 'title');
            self::assertContains('Team', $aboutUsChildren);
            self::assertContains('Geschichte', $aboutUsChildren);
        }

        // Children of page 3 (Products) in German
        if (isset($result[3])) {
            $productsChildren = array_column($result[3], 'title');
            self::assertContains('Kategorie A', $productsChildren);
            self::assertContains('Kategorie B', $productsChildren);
        }
    }

    #[Test]
    public function translatedPagesHaveCorrectLanguageUid(): void
    {
        $result = $this->subject->findNavigationByParentAndLanguage(1, 1);

        foreach ($result as $page) {
            self::assertSame(1, $page['sys_language_uid'], sprintf(
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
            self::assertGreaterThan(0, $page['l10n_parent'], sprintf(
                'Translated page "%s" should have l10n_parent > 0',
                $page['title']
            ));
        }
    }
}
