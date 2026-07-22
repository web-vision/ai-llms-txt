## Why

The extension has accumulated several fixes on top of `main` — some committed (injection escaping, N+1 batching, depth bounds, caching), some sitting uncommitted in the working tree (the multilingual navigation fix for GitHub issue #10) — without regression tests confirming they actually work, and without the GitHub issue tracker reflecting reality. Three open issues (#1, #2, #7) describe gaps or unconfirmed bugs that are only partially addressed in code today. Meanwhile the test harness (`runTests.sh`) still carries dead configuration copied from an unrelated extension (DeepL mock-server plumbing), and TYPO3 14 — which `composer.json` and CLAUDE.md already claim to support — has no CI workflow or phpstan config verifying it. "Hardening" here means: every reported bug either has a regression test proving it's fixed or is explicitly still open, and the test infrastructure itself is trustworthy and not testing conditions the extension doesn't have.

## What Changes

- Verify and land the uncommitted multilingual navigation fix (#10): confirm `NavigationBuilderBugTest` and `NavigationBuilderMultilingualTest` actually pass against the current `NavigationBuilder`/`PageRepository` code, then commit.
- Add a configurable doktype exclusion list to site configuration (`llmsTxtExcludeDoktypes`), replacing/extending the current hardcoded `PageRepository::EXCLUDED_DOKTYPES` constant, closing #1. Includes regression tests.
- Add a configurable cache lifetime to site configuration (`llmsTxtCacheLifetime`), replacing the hardcoded `LlmsTxtGeneratorService::CACHE_LIFETIME` constant, closing #2. Includes regression tests.
- Investigate and resolve the two unconfirmed sub-bugs from #7 (seo/description fields only populating in the default language on translated single-page markdown output; bodytext lost after image+text content elements) with a reproduction fixture and regression test each — or document why they no longer reproduce.
- Close out stale/superseded GitHub items: comment-and-close PR #9 (superseded by the current `SiteLanguage`-based overlay approach in `PageRepository::findById()`), comment-and-close PR #5 (targets a pre-refactor code shape; the multi-site half is independently fixed via #3, the doktype half is superseded by this change), close #8 once the trailing-slash fix ships with its existing test coverage, close #10 once verified.
- Clean up `Build/Scripts/runTests.sh`: remove leftover DeepL mock-server container logic (image, network, env vars) that belongs to a different extension and has no purpose here.
- Add TYPO3 14 CI coverage: `.github/workflows/testcore14.yml` and `Build/phpstan/Core14/phpstan.neon`, matching the already-claimed compatibility matrix.
- Fix `composer stan`, which was actually broken on a clean checkout (not merely "uncommitted and undecided"): `saschaegerer/phpstan-typo3` was intentionally dropped from `composer.json` in the 0.1.7 release, but the phpstan configs still referenced its (now-absent) `extension.neon` and TYPO3-specific parameters. Removed the dangling references and regenerated baselines for both TYPO3 majors.
- **Scope addition (post-proposal, per explicit user direction)**: drop TYPO3 12 support entirely (`composer.json`, `ext_emconf.php`, CLAUDE.md, `runTests.sh`, `composer-for-core-version.sh`, removing `testcore12.yml` and `Build/phpstan/Core12/`) — going forward only 13 and 14 are supported.
- **Scope addition (post-proposal, per explicit user direction)**: both `testcore12.yml` and `testcore13.yml` had their entire unit/functional test-execution job and PHPStan step commented out — CI was only linting, never running the actual test suite. Re-enabled PHPStan and a unit + functional-sqlite job in `testcore13.yml` and the new `testcore14.yml` (DB-variant functional steps for MariaDB/MySQL/Postgres intentionally left commented out to bound CI cost).
- Explicitly out of scope: issue #11 (RTE table export) — a feature request, not a bug, deferred to a future change.
- Explicitly out of scope for this apply session (per explicit user direction): no GitHub issue/PR comments or closes are drafted or executed. Section 5 of tasks.md is deferred to a future session.

## Capabilities

### New Capabilities
- `multilingual-navigation`: navigation tree building attributes every page (including deep tree levels) to its own language section only, with no cross-language leakage, for both the default-language BC path and the explicit-`SiteLanguage` path.
- `doktype-exclusion`: site-configurable list of page doktypes excluded from llms.txt navigation output, with children of excluded pages promoted rather than dropped.
- `response-caching`: site-configurable cache lifetime for generated llms.txt content, with a sane default when unconfigured.
- `markdown-page-output`: single-page markdown rendering (`.md` output) correctly localizes title/description/seo fields and preserves bodytext across content element types for the requested language.

### Modified Capabilities
- None — no `openspec/specs/` capabilities exist yet in this repo; all touched behavior is captured as new capability specs above.

## Impact

- **Code**: `Classes/Builder/NavigationBuilder.php`, `Classes/Repository/PageRepository.php`, `Classes/Service/ConfigurationService.php`, `Classes/Service/LlmsTxtGeneratorService.php`, `Classes/Controller/LlmsTxtController.php`, `Classes/Service/HtmlCleanerService.php` / `MarkdownConverterService.php` (pending #7 root-cause findings).
- **Config**: new site YAML keys `llmsTxtExcludeDoktypes`, `llmsTxtCacheLifetime`; `Configuration/SiteConfiguration/Overrides/sites.php` needs corresponding fields.
- **Tests**: functional tests under `Tests/Functional/` for each capability above; existing uncommitted test files (`NavigationBuilderBugTest`, `NavigationBuilderMultilingualTest`, `UrlGeneratorServiceTest`) get verified and folded in rather than duplicated.
- **CI/tooling**: `Build/Scripts/runTests.sh`, `Build/Scripts/composer-for-core-version.sh`, `Build/phpstan/Core13,14/phpstan.neon` (Core12 removed), `.github/workflows/testcore13.yml` (test execution re-enabled), `.github/workflows/testcore14.yml` (new), `.github/workflows/testcore12.yml` removed. `composer.json`/`ext_emconf.php` no longer declare TYPO3 12 support.
- **GitHub**: issues #1, #2, #7, #8, #10 and PRs #5, #9 would benefit from closing/resolution comments, but this was explicitly deferred out of scope for this apply session — no `gh` write actions were taken.
- **Out of scope**: issue #11 (RTE tables) is untouched by this change.
