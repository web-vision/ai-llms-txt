# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What This Extension Does

`ai_llms_txt` is a TYPO3 extension that generates `llms.txt` files per the [llmstxt.org](https://llmstxt.org/) specification, allowing TYPO3 websites to expose structured content for LLM crawlers. It also serves individual pages as Markdown via a `.md` URL suffix.

Two access patterns are supported:
- Query parameter: `?type=1699` (llms.txt), `?type=1701` (Markdown page)
- Route enhancer: `/.well-known/llms.txt`, `/<slug>.md`

## Commands

```bash
# Run all tests
composer test

# Unit tests only
composer test:unit

# Functional tests only
composer test:functional

# PHP syntax lint
composer test:php

# Check coding standards (PHP-CS-Fixer)
composer cs:check

# Fix coding standards
composer cs:fix

# PHPStan static analysis
composer stan

# Full CI pipeline
composer ci:test
```

Run a specific test suite with Docker/Podman via `Build/Scripts/runTests.sh`:
```bash
# -s suite, -t TYPO3 major version, -p PHP version
./Build/Scripts/runTests.sh -s unit -t 13 -p 8.3
./Build/Scripts/runTests.sh -s functional -t 13 -p 8.3 -d sqlite
```

## Architecture

### Request Flow

1. TYPO3 routes a request for `llms.txt` or `*.md` to the appropriate typeNum (1699 or 1701)
2. `LlmsTxtController` handles the request, delegates to `LlmsTxtGeneratorService` (caching disabled by default, controllable via `llmsTxtCacheLifetime`) or directly renders a single page as Markdown
3. For `llms.txt`, `NavigationBuilder` fetches the page tree in batches (no N+1 queries), `ConfigurationService` reads site YAML settings, and `UrlGeneratorService` produces absolute URLs
4. For Markdown pages, TYPO3's `ContentObjectRenderer` renders the page HTML, then `HtmlCleanerService` → `MarkdownConverterService` (via `league/html-to-markdown`) converts it

### Key Classes

| Class | Responsibility |
|-------|----------------|
| `Controller/LlmsTxtController` | Handles HTTP requests; entry point for both llms.txt and Markdown output |
| `Service/LlmsTxtGeneratorService` | Orchestrates llms.txt generation; manages cache (hash cache, disabled unless `llmsTxtCacheLifetime` is configured) |
| `Service/ConfigurationService` | Reads per-site YAML settings (`llmsTxtEnabled`, `llmsTxtMaxDepth`, etc.) |
| `Service/MarkdownConverterService` | HTML → Markdown conversion using `league/html-to-markdown` |
| `Service/HtmlCleanerService` | Strips TYPO3 navigation/header HTML before Markdown conversion |
| `Service/UrlGeneratorService` | Generates absolute frontend URLs for page records |
| `Builder/NavigationBuilder` | Builds the page hierarchy using batched DB queries; handles language fallback |
| `Repository/PageRepository` | All DB queries — applies TYPO3 page restrictions and language overlays |
| `Command/DownloadMarkdownCommand` | CLI: bulk-exports pages as `.md` files (`ai-llms:download-markdown`) |

### Configuration

Site-level settings live in the TYPO3 site configuration YAML (registered via `Configuration/SiteConfiguration/Overrides/sites.php`):

```yaml
llmsTxtEnabled: true
llmsTxtTitle: 'My Site'
llmsTxtDescription: 'About this site'
llmsTxtKeywords: 'topic1, topic2'
llmsTxtContactEmail: 'contact@example.com'
llmsTxtAdditionalInfo: 'Extra content block'
llmsTxtMaxDepth: 2   # 1–10, default 2
llmsTxtExcludeDoktypes: '3, 199'   # additional doktypes to exclude, on top of the always-excluded sysfolder/spacer/shortcut
llmsTxtCacheLifetime: 900   # seconds; 0 (default) disables caching entirely
```

TypoScript is loaded via `ext_localconf.php` and defined in `Configuration/TypoScript/setup.typoscript` (typeNum 1699) and `markdown.typoscript` (typeNum 1701).

Dependency injection is configured in `Configuration/Services.yaml` using Symfony DI autowiring.

### Testing Conventions

- PHPUnit 10+/11+ with `#[Test]` attribute (not `test` method prefix)
- Unit tests: `Tests/Unit/` — no TYPO3 bootstrap needed
- Functional tests: `Tests/Functional/` — use TYPO3 TestingFramework; test fixtures in `Build/Scripts/testdata.sql`
- All classes use `declare(strict_types=1)` and fully typed signatures

## TYPO3 Compatibility

| TYPO3 | PHP |
|-------|-----|
| 12.4  | 8.2 |
| 13.4  | 8.2, 8.3, 8.4 |
| 14.1  | 8.2, 8.3, 8.4 |
