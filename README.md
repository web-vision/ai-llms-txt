# LLMS TXT Generator for TYPO3

[![TYPO3 13](https://img.shields.io/badge/TYPO3-13.4-orange.svg)](https://get.typo3.org/version/13)
[![TYPO3 14](https://img.shields.io/badge/TYPO3-14.3-orange.svg)](https://get.typo3.org/version/14)
[![PHP 8.2-8.4](https://img.shields.io/badge/PHP-8.2--8.4-blue.svg)](https://www.php.net/)
[![License: GPL v2+](https://img.shields.io/badge/License-GPL%20v2+-blue.svg)](https://www.gnu.org/licenses/gpl-2.0)

Generates an `llms.txt` file according to the [llmstxt.org specification](https://llmstxt.org/)
and serves pages as Markdown, giving Large Language Models a structured, machine-readable
view of your TYPO3 site.

## Features

- **llms.txt generation** at `/llms.txt` and `/.well-known/llms.txt`, listing the site's page
  tree with links and descriptions
- **Markdown version of every page** by appending `.md` to its URL, rendered through TYPO3's
  regular content rendering
- **Per-site configuration** of title, summary, topics, contact address, depth and excluded
  page types
- **Respects page visibility**: hidden, access-restricted, "hide in menu" and "no index" pages
  are not listed
- **Optional caching** of the generated llms.txt
- **CLI export** of pages as Markdown files (`ai-llms:download-markdown`)

## What is llms.txt?

`llms.txt` is a proposed convention for a Markdown file at the root of a website that tells
language models what the site is about and where its most useful content is. Language models
struggle with full HTML pages and have limited context windows; a curated index with links to
clean Markdown versions lets them find and read the relevant content directly.

`llms.txt` is **not** an access control mechanism: it does not grant or deny permission to
crawl or train on your content. Use `robots.txt` and your server configuration for that.

## Requirements

- **TYPO3**: 13.4 LTS or 14.3 LTS
- **PHP**: 8.2, 8.3 or 8.4

## Installation

```bash
composer require web-vision/ai-llms-txt
vendor/bin/typo3 extension:setup
```

The extension works immediately and is enabled for all sites:

- `https://example.com/?type=1699` returns `llms.txt`
- `https://example.com/about?type=1701` returns the Markdown version of `/about`

For the usual URLs, import the route enhancer into your site configuration:

```yaml
# config/sites/main/config.yaml
imports:
  -
    resource: 'EXT:ai_llms_txt/Configuration/Routes/RouterEnhancer.yaml'
```

This enables `/llms.txt`, `/.well-known/llms.txt` and the `.md` suffix, for example
`https://example.com/about.md`.

## Configuration

All settings are made per site, in the backend module **Site Management > Sites**
(tab *LLMS.TXT Configuration*) or directly in the site's `config.yaml`:

```yaml
llmsTxtEnabled: true            # Enables llms.txt and the .md pages for this site
llmsTxtTitle: 'ACME Corporation'   # Default: title of the root page
llmsTxtDescription: 'Industrial products and engineering services.'   # Default: root page description
llmsTxtKeywords: 'manufacturing, engineering'
llmsTxtContactEmail: 'contact@example.com'
llmsTxtAdditionalInfo: 'Free Markdown appended to the end of llms.txt'
llmsTxtMaxDepth: 2              # Page tree levels to list, 1-10
llmsTxtExcludeDoktypes: '3, 199'   # Additional page types to leave out
llmsTxtCacheLifetime: 900       # Seconds; 0 (default) disables caching
```

See the [configuration reference](Documentation/Configuration/Index.rst) for details, including
which pages are listed.

## Documentation

- [Introduction](Documentation/Introduction/Index.rst)
- [Installation, URLs, CLI export and troubleshooting](Documentation/Administrator/Index.rst)
- [Configuration reference](Documentation/Configuration/Index.rst)
- [Editor guidelines](Documentation/Editor/Index.rst)
- [Architecture, public API and testing](Documentation/Developer/Index.rst)
- [Known problems](Documentation/KnownProblems/Index.rst)
- [Changelog](CHANGELOG.md)

## Contributing

Contributions are welcome. Please follow the TYPO3 coding guidelines, include tests for new
behaviour and document new settings. See the
[developer documentation](Documentation/Developer/Index.rst#testing) for how to run the tests.

## License

This extension is licensed under GPL v2+ - see the [LICENSE](LICENSE) file for details.

## Related

- [llmstxt.org](https://llmstxt.org/) - Specification
- [TYPO3 Documentation](https://docs.typo3.org/) - TYPO3 CMS documentation
