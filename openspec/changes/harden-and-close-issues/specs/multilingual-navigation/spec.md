## ADDED Requirements

### Requirement: Deep-tree pages are attributed to their own language section only
`NavigationBuilder` SHALL fetch subpages at every tree level using the language of the parent page (or the requested `SiteLanguage`, when provided), so that no page from one language ever appears as a child, grandchild, or deeper descendant of a page in a different language section.

#### Scenario: Default-language section contains only default-language descendants
- **WHEN** `NavigationBuilder::build()` is called without a `SiteLanguage` (the backward-compatible path) on a site with pages translated into German and French at tree level 2 and 3
- **THEN** the "Default language" section's children and grandchildren all report `language === 'Default language'`, and none of their URLs contain the German or French URL path segments

#### Scenario: Explicit SiteLanguage returns only that language's tree
- **WHEN** `NavigationBuilder::build()` is called with an explicit German `SiteLanguage`
- **THEN** the returned top-level pages are only the German-language pages, and their children are only German-language children — no English or French top-level pages or descendants appear in the result

#### Scenario: Rendered markdown groups children under the correct language header
- **WHEN** `NavigationBuilder::formatAsMarkdown()` renders a navigation structure spanning three languages with deep trees
- **THEN** each `## <Language>` markdown section contains only the page titles belonging to that language, with no cross-language leakage in any section

### Requirement: Language attribution survives regardless of l10n_parent vs uid join key usage
Recursive child-fetching SHALL consistently use each page's `l10n_parent` (when set) as the join key for finding its own children, so that translated pages' children are found via the correct parent identity at every recursion depth.

#### Scenario: Translated page's children are found via l10n_parent
- **WHEN** a German page has `l10n_parent` pointing to its default-language original, and that German page has its own German-language children
- **THEN** `NavigationBuilder::build()` returns those German children nested under the German page, not omitted and not nested under the default-language original
