## Context

`main` (commit `c8edad7`) already contains a round of hardening (`63005e1`, `f236516`) — markdown-injection escaping, batched navigation queries, `maxDepth` bounds, response caching, and a switch to Core's `PageRepository` for language overlays. On top of that, the working tree currently has **uncommitted** changes implementing the fix for GitHub #10 (deep-tree pages leaking across language sections) plus three new test files (`NavigationBuilderBugTest`, `NavigationBuilderMultilingualTest`, `UrlGeneratorServiceTest`) and a new fixture (`pages_multilingual_deep.csv`). None of this has been run yet.

Nine GitHub issues and two PRs exist against this extension. Cross-referencing them with the current code (done during exploration) shows: #3, #6, #8 are fixed in code but #8 is still open on the tracker; #10's fix is unverified; #1 and #2 are half-solved (behavior exists but isn't configurable, which is what the reporters asked for); #7 was closed after two of its four complaints were confirmed fixed, but two sub-bugs (localized seo/description fields, bodytext loss after image+text content elements) were never actually confirmed or denied — the thread just went quiet waiting on a repro the reporter said they'd email. PR #9 and PR #5 both predate the current architecture and don't cleanly apply.

Separately, `Build/Scripts/runTests.sh` was forked from `web-vision/deepl-write` and still starts a DeepL mock-server container for every functional test run — dead weight for an extension that has nothing to do with translation APIs. TYPO3 14 is claimed as supported (`composer.json`, CLAUDE.md) but has no CI workflow or phpstan config, and the working tree currently has an uncommitted, unexplained removal of the `saschaegerer/phpstan-typo3` include from both existing phpstan configs.

## Goals / Non-Goals

**Goals:**
- Every currently-uncommitted fix (#10) is verified by actually running its tests before it's trusted or committed.
- #1 and #2 get the configurability their reporters actually asked for, not just the hardcoded behavior that happens to help.
- #7's two dangling sub-bugs get a definitive answer (fixed-with-test, or confirmed-not-reproducible-with-evidence) instead of staying in limbo.
- The test runner only models infrastructure this extension actually needs.
- The claimed TYPO3 12/13/14 support matrix has CI enforcing it end to end.
- GitHub issue/PR state matches reality by the end of the change.

**Non-Goals:**
- Issue #11 (RTE table export) — explicitly deferred, not a bug.
- Any new product feature not already requested in an open issue.
- Rewriting `runTests.sh` wholesale — only removing dead DeepL-specific plumbing and adding the missing TYPO3 14 lane, informed by patterns in `calien666/typo3-xlsexport`'s runner (e.g. its `composerInstallMax` fix that forces the resolver to actually pin the `-t` major via a temporary `typo3/minimal` requirement, and its `TYPO3_PATH_ROOT`/`TYPO3_PATH_WEB` env vars for the unit suite) — adopt only what's relevant, not a wholesale copy.

## Decisions

**1. Verify #10 before anything else, in isolation.** Run the existing uncommitted functional tests (`NavigationBuilderBugTest`, `NavigationBuilderMultilingualTest`) against the uncommitted `NavigationBuilder`/`PageRepository` changes via `runTests.sh -s functional`, unmodified, before touching any other file. If they pass, commit that fix on its own (small, reviewable, bisectable). If they fail, fix forward before layering more work on top of a bug fix nobody has confirmed works. This is deliberately sequential, not parallelized with the other tracks, because everything else builds on trusting the test harness and the language-overlay code path.

**2. `llmsTxtExcludeDoktypes` as a comma-separated site-config string, following the existing `llmsTxtKeywords` convention.** `ConfigurationService::getKeywords()` already parses a comma-separated site YAML value into an array — reuse that exact pattern for symmetry rather than inventing a new config shape (e.g. YAML list). The configured list is **additive** to the existing hardcoded `EXCLUDED_DOKTYPES` (sysfolder/spacer/shortcut) rather than replacing it — those three are structural, not-really-pages doktypes whose exclusion nobody configuring a custom list should have to remember to preserve. Default value: empty, meaning only the existing hardcoded exclusions apply — nothing changes for sites that don't configure it.
- *Alternative considered*: YAML array (`llmsTxtExcludeDoktypes: [3, 6, 254]`). Rejected only for consistency with `llmsTxtKeywords`'s existing string convention — either would work technically.
- *Alternative considered*: configured list replaces the hardcoded list entirely. Rejected — too easy for an operator to configure a custom list, forget to include the folder doktype, and have sysfolders start appearing in output.

**3. `llmsTxtCacheLifetime` as an integer seconds site-config value, defaulting to `0` (caching disabled unless explicitly configured).** This is a deliberate behavior change from the pre-existing hardcoded 3600s cache — the user directing this change chose "off by default, operator opts in" over "on by default, operator opts out." Read in `LlmsTxtGeneratorService` the same way `ConfigurationService::getMaxDepth()` reads and bounds `llmsTxtMaxDepth`. Apply a sane floor (reject values `< 0`); `0` and unconfigured are the same state (no caching). No arbitrary ceiling — cache lifetime doesn't have the same DoS shape `maxDepth` does.

**4. #7's sub-bugs get investigated with a purpose-built repro fixture before any fix is attempted.** Both complaints are plausible but unconfirmed:
   - *Localized seo_title/description*: current `PageRepository::findById(int, ?SiteLanguage)` already overlays via Core's `getPagesOverlay()` — this may already be fixed as a side effect of the #10-adjacent refactor. Write a functional test with a translated page (non-empty `seo_title`/`description` only on the translation record) and assert the `.md` output for that language shows the translated values, not a fallback to default language. If it already passes, close with "fixed as a side effect of the PageRepository refactor, test added for regression coverage" rather than writing speculative fix code.
   - *Bodytext lost after image+text content elements*: this lives in `HtmlCleanerService`/`MarkdownConverterService`, untouched by the #10 work. Requires building a page fixture with a `textmedia` (or equivalent image+text) CE followed by a plain text CE, rendering it, and checking the plain text CE's content survives. If it reproduces, the fix is scoped to whatever selector/strip rule in `HtmlCleanerService::cleanTypo3Html()` is over-matching. If it doesn't reproduce against current `main`, document the negative result (what was tried, what TYPO3 version/CE config) as the closing comment on #7 rather than closing silently.

**5. Stale GitHub write actions are gated behind explicit user confirmation, done as one deliberate step at the end.** Closing issues/PRs and posting comments on `web-vision/ai-llms-txt` is visible to external reporters and is not reversible in spirit even though GitHub allows reopening. Tasks.md will list the exact `gh issue close` / `gh pr close` / `gh issue comment` commands with their proposed comment text as a single reviewable batch, executed only after the user reviews and approves it — not silently as a side effect of the code changes landing.

**6. `runTests.sh` cleanup is additive-safe: remove, don't restructure.** Only strip what's demonstrably dead (the `IMAGE_DEEPL` container, its network wiring, the `DEEPL_*` env vars injected into every functional run, the stray `echo "Using deepl-mockserver"`). Add a `testcore14.yml` workflow and `Build/phpstan/Core14/phpstan.neon` modeled directly on the existing Core13 equivalents (same structure, just the version bumped) rather than adopting the reference repo's broader restructuring (different check suites like `checkIntegrityXliff`, different flag set) — those check suites don't apply to this extension's setup and pulling them in is scope creep.

**7. The uncommitted `saschaegerer/phpstan-typo3` extension.neon removal is treated as a decision point, not a fait accompli.** It's already sitting in the working tree without an explanation in any commit message. Before doing anything else with the phpstan configs, run `composer stan` both with and without that include restored to see whether it was removed because the package is no longer installed/compatible (in which case removal is correct and should be documented) or was an accidental side effect of some other edit (in which case restore it — losing TYPO3-specific static analysis rules silently would be a real regression in the "harden" story this whole change is about).

## Risks / Trade-offs

- **[Risk] The #10 fix might not actually pass its own tests.** → Mitigation: Decision 1 makes this the very first task, run in isolation, before any other work depends on it.
- **[Risk] #7's sub-bugs might not reproduce, leaving an unsatisfying "we don't know" for the reporter.** → Mitigation: Decision 4 treats a documented negative result as an acceptable, honest outcome — better than a silent close or a speculative fix for a bug that may not exist.
- **[Risk] Changing `EXCLUDED_DOKTYPES` from a hardcoded constant to config-driven could silently change output for sites that already rely on the current fixed exclusion list.** → Mitigation: Decision 2's default (empty config = current hardcoded list still applies) makes this purely additive; add a regression test asserting default behavior is unchanged when the config key is absent.
- **[Risk] Posting GitHub comments/closes on issues reported by real external users is visible and not something to get wrong or do unilaterally.** → Mitigation: Decision 5 — batch, reviewable, explicit approval gate, no auto-execution.
- **[Trade-off] Investigating #7 before fixing it costs time that "just write a test for the reported issue" would skip.** → Accepted: writing a regression test for a bug that may already be fixed elsewhere, or may not exist, produces false confidence; the investigation is the point.
- **[Risk] Defaulting `llmsTxtCacheLifetime` to `0` removes the existing 1h cache for every site that doesn't explicitly configure the new key — a real behavior change, not purely additive.** → Accepted per explicit direction: sites that want the old behavior back set `llmsTxtCacheLifetime: 3600`. Documented clearly in CLAUDE.md and the release notes for this version so operators aren't surprised by a regeneration-per-request change.

## Migration Plan

1. Verify + commit #10 fix alone.
2. Implement + test `llmsTxtExcludeDoktypes` and `llmsTxtCacheLifetime` (independent of each other, can proceed in either order).
3. Investigate #7's two sub-bugs; implement fixes only for what reproduces.
4. Clean `runTests.sh`, add TYPO3 14 CI/phpstan, resolve the phpstan-typo3-extension question.
5. Run the full `composer ci:test` pipeline once, across all three TYPO3 majors, as a final gate.
6. Batch-review and execute the GitHub issue/PR closing actions.

No database migration needed. Most changes are additive site-config keys with backward-compatible defaults, test-only, or CI-tooling-only — the one exception is the cache lifetime default (see Risks above), which is an intentional behavior change. No rollback beyond normal git revert.

## Open Questions

- Does #7's bodytext-loss bug still reproduce on current `main`, or was it incidentally fixed by unrelated work since January? (Decision 4 — answer via investigation, not assumption.)
- Was the `saschaegerer/phpstan-typo3` extension.neon removal intentional (package dropped/incompatible) or accidental? (Decision 7 — answer via `composer stan` experiment.)
- Should `llmsTxtCacheLifetime: 0` mean "disable caching" or "use default"? Decision 3 proposes "disable" (matches how a debugging operator would expect `0` to behave) — confirm this reading before implementing.
