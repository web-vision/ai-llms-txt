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

## Manufacturer

web-vision GmbH<br>
An der Eickesmühle 38<br>
41238 Mönchengladbach<br>
Germany

- **Contact:** hello@web-vision.de
- **Security reports:** https://security.web-vision.de

## Security environment & security properties

The extension runs inside TYPO3 CMS and relies on it for authentication, access control,
database access and HTTP handling. Operate it only on a TYPO3 and PHP version that still
receives security updates.

- **Read-only outputs.** The extension adds two public, read-only frontend outputs (llms.txt
  and the Markdown version of pages) and one CLI command. It has no backend module, no forms
  and no database tables of its own. It writes only cache entries and, when the CLI command is
  run, export files.
- **Only what the visitor may see.** Pages are read with TYPO3's frontend restrictions
  (hidden, deleted, start and stop time, frontend user groups). Pages hidden in menus or
  marked "no index" are not listed. The Markdown version of a "no index" page, and of any
  page of a site where the extension is disabled, answers with "page not found".
- **Safe output.** llms.txt is sent as `text/plain`, the Markdown version as `text/markdown`,
  both with `X-Content-Type-Options: nosniff`.
  Values in llms.txt are escaped and kept on a single line, and `<` and `>` remain encoded in
  the Markdown output, so escaped markup never turns into live HTML.
- **No internal details in responses.** Errors are logged through the TYPO3 logging framework;
  responses contain only a generic message.
- **Separated caches.** Cached output is kept separate per combination of frontend user
  groups, so content of access-restricted pages is never served to other visitors.
- **No outgoing connections** during web requests. Only the CLI command makes HTTP requests,
  to the site's own Markdown URLs.
- **No personal data** of visitors is processed or stored.

## Known risks & foreseeable misuse

The overall risk is low: the extension republishes content that is already public on the
website, in a different format. The remaining risks are:

- **Content of unused columns becomes public.** The Markdown version contains the content
  elements of all columns of a page, including columns that the page template does not
  display. Content parked in such a column is published. Hide or delete content elements that
  must not be public.
- **llms.txt is not access control.** It does not prevent crawling or the use of content for
  AI training. Use `robots.txt` and the web server configuration for that.
- **Text aimed at language models.** Anyone who can edit page content or the site settings
  can place text in the output that language models will read, including instructions meant
  to manipulate them. Grant editing permissions accordingly.
- **Free-form content.** The setting `llmsTxtAdditionalInfo` is written to llms.txt as-is.
- **Exported files.** Files written by the CLI command contain page content. If the output
  directory is inside the web root, they become publicly accessible.
- **Server load.** Without a cache lifetime, llms.txt is generated on every request. On
  high-traffic sites, set `llmsTxtCacheLifetime`.

## Installation & secure initial setup

1. Install with `composer require web-vision/ai-llms-txt` and run
   `vendor/bin/typo3 extension:setup`.
2. The extension is enabled for all sites right after installation. Set `llmsTxtEnabled: false`
   for sites that should not offer llms.txt and Markdown versions, such as staging sites.
3. Before relying on it for a site, check for content elements in columns that the page
   template does not display, and mark pages that should not be listed as "no index" or
   "hide in menu".
4. Import the route enhancer (see [Installation](#installation)).
5. On high-traffic sites, set `llmsTxtCacheLifetime`.

## Configuration changes & data security

- **Who can change settings.** All settings are part of the site configuration. In the
  backend, only administrators can change them (module *Site Management > Sites*). They are
  stored in `config/sites/<identifier>/config.yaml`; protect that file like the rest of the
  project configuration.
- **Published values.** Title, description, keywords, contact address and additional
  information are published in llms.txt. Only enter a contact address that is meant to be
  public.
- **Caches.** With `llmsTxtCacheLifetime` set, the generated output is stored in TYPO3's
  page and hash caches (in the database by default). Flushing the TYPO3 caches removes it.
- **Exports.** `ai-llms:download-markdown` writes to `var/markdown-export/` by default, which
  is outside the web root in Composer installations. Do not point `--output-dir` into the
  public directory, and delete exports that are no longer needed.

## Supported Versions

| Version | Supported          | End of Support |
|---------|--------------------|----------------|
| 0.x     | :white_check_mark: | 2029-06-30     |

## Security

Found a vulnerability? Please report it privately via our
[security report form](https://security.web-vision.de) — **do not** open a public issue.
See [SECURITY.md](SECURITY.md) for the full vulnerability disclosure policy,
including what to expect and our safe harbor statement.

## Simplified EU Declaration of Conformity (Annex VI)

> Hereby, web-vision GmbH declares that the product with digital elements
> type LLMS TXT Generator is in compliance with Regulation (EU) 2024/2847.
>
> The full text of the EU declaration of conformity is available at the
> following internet address:
> https://security.web-vision.de/conformity/web-vision/ai-llms-txt/0.3.0/en/

The full declarations are also included in this repository:
[English](EU-Declaration-of-Conformity.md) ·
[Deutsch](EU-Konformitaetserklaerung.md).
