## 1. Verify and land the uncommitted #10 fix

- [x] 1.1 Run `./Build/Scripts/runTests.sh -s functional -t 13 -p 8.3 -d sqlite` against the current working tree and confirm `NavigationBuilderBugTest` and `NavigationBuilderMultilingualTest` pass unmodified — all 80 functional tests passed (with `CI=true` to avoid the `-it` TTY flag, since this session has no attached terminal)
- [x] 1.2 Run the existing `NavigationBuilderTest`, `PageRepositoryTest`, and `UrlGeneratorServiceTest` to confirm no regressions from the uncommitted changes — 80 functional + 28 unit tests, all green
- [x] 1.3 If any test fails, fix forward — not needed, suite was green. Along the way, fixed an unrelated but blocking bug: `composer.json`'s `extra.typo3/cms` used lowercase `package` instead of the `Package` key TYPO3 core actually checks in `PackageManager::isComposerOnlyCapable()`, which made every test run trigger a deprecation warning that `failOnDeprecation="true"` turned into a spurious suite-wide FAILURE. Fixed the casing and populated `providesPackages`.
- [x] 1.4 Committed as three separate commits instead of one, since investigation revealed the uncommitted working tree actually contained fixes for three separate issues sharing file history: `0e090f8` (#10 navigation fix), `aa6b5cb` (#7's two sub-bugs — see section 4), `858bd03` (#8 trailing-slash fix + its unit tests). The `composer.json` manifest fix is still pending, to be committed with section 6 (test infrastructure).

## 2. Configurable doktype exclusion (closes #1)

- [x] 2.1 Added `getExcludeDoktypes(): array` to `ConfigurationService`, parsing `llmsTxtExcludeDoktypes` the same way `getKeywords()` parses `llmsTxtKeywords`
- [x] 2.2 Wired the parsed list into `PageRepository`, unioned with the existing hardcoded `EXCLUDED_DOKTYPES`. Along the way, found and fixed a real pre-existing gap: `findNavigationByParentsBatch()`/`findNavigationByParentsBatchWithFallback()` (used for tree depth 2+) had **no** doktype exclusion logic at all, unlike the level-1 path — now both apply the same exclusion+promotion recursively.
- [x] 2.3 Documented `llmsTxtExcludeDoktypes` in `CLAUDE.md`, the site config YAML example, `locallang.xlf`, and registered the site-config column in `Configuration/SiteConfiguration/Overrides/sites.php`
- [x] 2.4 Functional test added: configured doktype excluded, its children promoted (`findNavigationByParentExcludesConfiguredDoktypeAndPromotesItsChildren`)
- [x] 2.5 Functional test added: unconfigured site keeps default exclusion behavior unchanged (`findNavigationByParentUnconfiguredKeepsDefaultExclusionsUnchanged`)
- [x] 2.6 Functional test added: custom list doesn't un-exclude sysfolder (`configuredExcludeListDoesNotUnexcludeStructuralDoktypes`). Also added `findNavigationByParentsBatchExcludesAndPromotesAtDeeperLevels` covering the depth-2+ gap found in 2.2. Making `NavigationBuilder` depend on `ConfigurationService` required it to gracefully degrade (empty exclude list) when no request context is available, to avoid breaking existing request-context-free usage (e.g. `NavigationBuilderMultilingualTest`, `NavigationBuilderBugTest`). Committed as `86a804f`.

## 3. Configurable cache lifetime (closes #2)

- [x] 3.1 Added `getCacheLifetime(): int` to `ConfigurationService`, reading `llmsTxtCacheLifetime`, defaulting to `0` (caching disabled unless explicitly configured — user directive, overrides original 3600 default)
- [x] 3.2 Wired into `LlmsTxtGeneratorService` in place of the hardcoded `CACHE_LIFETIME` constant
- [x] 3.3 `0` disables caching (skips both cache read and cache write) via an early return before any cache interaction
- [x] 3.4 Documented in `CLAUDE.md` and the site config YAML example, `locallang.xlf`, and the site-config column, explicitly noting the default-disabled behavior change from the previous hardcoded 1h cache
- [x] 3.5 Functional tests added in `LlmsTxtGeneratorServiceTest` (`configuredCacheLifetimeStoresAndServesCachedContent` — verifies both the write and that a second call reads from cache rather than regenerating)
- [x] 3.6 Test added: unconfigured site has no caching (`unconfiguredCacheLifetimeDefaultsToDisabled`)
- [x] 3.7 Test added: lifetime 0 skips cache read/write (`cacheLifetimeZeroSkipsCacheReadAndWrite`). Discovered along the way: TYPO3's `FunctionalTestCase` forces the "hash" cache to `NullBackend` by default (to save test setup time), which made caching unobservable in tests until `$configurationToUseInTestInstance` was used to restore the real `Typo3DatabaseBackend` for this specific test class. Committed as `86a804f`.

## 4. Investigate and resolve #7's dangling sub-bugs

- [x] 4.1 Build a functional fixture: a page with a French translation carrying distinct `seo_title`/`description`, request its `.md` output under the French language context — done via `PageRepositoryTest::findByIdReturnsTranslatedDataWithSiteLanguageData` (German fixture, same mechanism) plus the `seo_title` column added to `pages.csv`
- [x] 4.2 Already fixed on inspection: `LlmsTxtController` (uncommitted) already reads `$request->getAttribute('language')` and passes it to `findById()`, preferring `seo_title`. Confirmed via the existing repository-level test passing. Closed as "already fixed, test added" — see commit `aa6b5cb`.
- [x] 4.3 Not needed — 4.2 confirmed it already works.
- [x] 4.4 Built a functional fixture in `MarkdownConverterServiceTest::convertHtmlToMarkdownPreservesTextContentElementAfterTextmediaElement`: realistic `fluid_styled_content`-shaped textmedia CE (figure/figcaption/img) immediately followed by a separate text CE.
- [x] 4.5 Rendered through the full `convertHtmlToMarkdown()` pipeline (which calls `cleanTypo3Html()` internally) — passes with the uncommitted `figure`-removed-from-`remove_nodes` fix in place.
- [x] 4.6 Root cause identified: `HtmlCleanerService::cleanTypo3Html()` step 3 removes matched wrapper `<div>` *opening* tags but then unconditionally strips **every** `</div>` in the document (`preg_replace('/<\/div>/i', '', $html)`), leaving dangling unclosed divs that a DOM parser can renest unpredictably. The existing `remove_nodes: figure` fix mitigates the reported symptom (content getting swallowed when a renested subtree was fully deleted) without fixing the root fragility. Documented as a known latent issue in the commit message rather than rewritten now — regex can't reliably track arbitrary nesting depth; a real fix would need a DOM-based unwrap instead of regex, which is a larger, riskier change than this investigation's scope justified once the reported symptom no longer reproduces.

## 5. Close out stale/superseded GitHub items — OUT OF SCOPE for this apply session

- [ ] 5.1-5.8 Deferred per explicit user instruction: "Don't commit to or update issues on GitHub." No drafting or execution of `gh issue`/`gh pr` comment/close actions happens in this change. Revisit in a future session if/when the user wants to close the loop with reporters.

## 6. Test infrastructure hardening

- [x] 6.1 Removed leftover DeepL mock-server plumbing from `Build/Scripts/runTests.sh` (`IMAGE_DEEPL`, its container/network wiring, `DEEPL_*` env vars) from the `functional` case. Confirmed dead: the pre-fix run actually pulled `ghcr.io/web-vision/wv-deeplmockapi-server` on every functional test invocation. Renamed the docker network from `wv-deepl-write-*` to `ai-llms-txt-*` and fixed the header comment. Committed as `9b2dcfc`.
- [x] 6.2 Ran `composer stan` — it was actually **broken on a clean checkout** (not merely an "uncommitted change to evaluate"): `saschaegerer/phpstan-typo3` was intentionally dropped from `composer.json` in the "Release 0.1.7" commit (`23a8d02`), but `Build/phpstan/Core12/Core13/phpstan.neon` still referenced its `extension.neon` and TYPO3-specific `typo3:` parameter block, which `git log -S` confirms were never updated to match. `composer stan` failed with "file is missing" on HEAD.
- [x] 6.3 Confirmed intentional per 6.2's git history — removed the dangling include and the `typo3:` parameter block (which only that extension understands) from both configs. Regenerated `phpstan-baseline.neon` for both TYPO3 majors via `composer stan:baseline`, since dropping the extension's TYPO3-aware type stubs surfaces 55 previously-hidden strictness findings across the existing codebase — captured as a baseline snapshot rather than fixed individually (out of scope for this change). `composer stan` now passes cleanly. Committed as `e1f3ab2`.
- [x] 6.4 Added `Build/phpstan/Core14/phpstan.neon`, baseline generated against a real installed TYPO3 14.3.0 (already resolved in `.Build/vendor`) via `composer stan:baseline -t 14`.
- [x] 6.5 Added `.github/workflows/testcore14.yml` modeled on the fixed `testcore13.yml`.
- [x] 6.6 Ran unit, functional (sqlite), phpstan, lintPhp, and cgl individually across TYPO3 13 and 14 — all green. **Scope change per explicit user direction**: TYPO3 12 support is dropped entirely (composer.json, ext_emconf.php, CLAUDE.md, runTests.sh defaults/validation, `composer-for-core-version.sh`, `.github/workflows/testcore12.yml` and `Build/phpstan/Core12/` removed). Also discovered and fixed, per user approval, that both `testcore12.yml` and `testcore13.yml` had their entire test-execution job (unit + functional) and the PHPStan step commented out — CI was only linting, never actually running the test suite. Re-enabled PHPStan + a unit/functional-sqlite job in both `testcore13.yml` and the new `testcore14.yml` (DB-variant functional steps for MariaDB/MySQL/Postgres left commented out to keep CI cost down, per user's choice among options presented). Committed as `c2af053` and `4f2fda6`.

## 7. Final verification

- [x] 7.1 Ran unit, functional (sqlite), phpstan, lintPhp, and cgl one more time end to end for both TYPO3 13 and TYPO3 14 after all sections above complete — all green.
- [x] 7.2 Reconciled `openspec/specs/` against the shipped implementation: added a scenario to `doktype-exclusion/spec.md` explicitly covering the depth-2+ batch exclusion/promotion fix found during 2.2, which wasn't in the original proposal scope. `response-caching` and `markdown-page-output` specs already matched. `multilingual-navigation` matched the verified #10 fix as-is.
