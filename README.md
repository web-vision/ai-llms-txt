# LLMS TXT Generator for TYPO3

[![TYPO3 13](https://img.shields.io/badge/TYPO3-13.4-orange.svg)](https://get.typo3.org/version/13)
[![TYPO3 14](https://img.shields.io/badge/TYPO3-14.3-orange.svg)](https://get.typo3.org/version/14)
[![PHP 8.2-8.5](https://img.shields.io/badge/PHP-8.2--8.5-blue.svg)](https://www.php.net/)
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
- **PHP**: 8.2, 8.3, 8.4 or 8.5

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

- **Website:** https://www.web-vision.de
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

## Installing security updates

Security fixes are released as new versions on
[Packagist](https://packagist.org/packages/web-vision/ai-llms-txt) and in the
[TYPO3 Extension Repository](https://extensions.typo3.org/extension/ai_llms_txt). The
[changelog](CHANGELOG.md) lists them under *Security*. Versions with the same minor version
(for example 0.3.x) contain no breaking changes, so they can be installed without adapting the
project.

- **Composer:** run `composer update web-vision/ai-llms-txt` and deploy as usual. With the
  constraint `^0.3`, Composer installs every 0.3.x release. `composer outdated` shows newer
  versions; `composer audit` reports installed versions with a published security advisory.
- **Without Composer:** update the extension in *Admin Tools > Extensions* after updating the
  extension list.
- Then flush the TYPO3 caches (`vendor/bin/typo3 cache:flush`) so that output generated by the
  previous version is not served from the cache.

Before updating to a new minor version, read the changelog for breaking changes.

## Uninstallation & data removal

The extension has no database tables of its own and stores no personal data. To remove it
completely:

1. Remove the import of `EXT:ai_llms_txt/Configuration/Routes/RouterEnhancer.yaml` from each
   site configuration. Otherwise TYPO3 logs an error every time it reads the site
   configuration.
2. Remove the extension: `composer remove web-vision/ai-llms-txt`, or deactivate and delete it
   in *Admin Tools > Extensions*.
3. Remove the `llmsTxt*` settings from `config/sites/<identifier>/config.yaml`. TYPO3 ignores
   them once the extension is gone, but they still contain the values entered.
4. Flush the TYPO3 caches (`vendor/bin/typo3 cache:flush`). This removes the cached llms.txt
   and Markdown output from the page and hash caches.
5. Delete the files exported with `ai-llms:download-markdown` (by default in
   `var/markdown-export/`, or in the directory passed with `--output-dir`).

Afterwards `/llms.txt` and the `.md` URLs answer with "page not found". Copies that crawlers
and language models have already fetched are outside your and our control and are not removed
by uninstalling.

## Automatic security updates

The extension does not update itself and has no update mechanism of its own; it makes no
connection to web-vision. Updates are installed only by whoever operates the TYPO3
installation, through Composer or the Extension Manager, so there is no automatic update
setting to switch off. If the project uses a tool that updates dependencies automatically,
such as Renovate or Dependabot, that tool's configuration decides whether updates of this
extension are applied automatically.

## Notes for integrators

For agencies and developers who deliver this extension as part of a TYPO3 project:

- **Dependencies.** The extension uses TYPO3 core packages and `league/html-to-markdown`. The
  [SBOM](#software-bill-of-materials-sbom) lists all components and versions.
- **Updates and support.** Include the extension in your update process and pass on security
  updates to the operators of your projects. Security updates are provided until the end date
  under [Supported Versions](#supported-versions).
- **Stable interface.** The site settings, the page types 1699 and 1701, the route enhancer,
  the output format and the CLI command are covered by semantic versioning. The PHP classes
  are internal. If you replace a service through dependency injection, the security of your
  replacement is your responsibility.
- **Page types.** The extension uses the page types 1699 and 1701. Make sure your project does
  not use them for something else.
- **Treat the output as untrusted.** The outputs contain content written by editors. Systems
  that process them, such as an application rendering the Markdown as HTML or a language model
  reading llms.txt, must treat them as untrusted input: render Markdown with raw HTML disabled
  and only safe link schemes, and do not let instructions in the content control the model.
- **Your own conformity assessment.** The EU declaration of conformity below covers this
  extension only. Projects that integrate it can refer to it and to the SBOM in their own
  documentation.
- **Vulnerabilities.** Report vulnerabilities you find in the extension via
  https://security.web-vision.de, also when you found them in a customer project.

## Software Bill of Materials (SBOM)

An SBOM in CycloneDX 1.6 JSON format is generated automatically for every release and every
change to the main branch. Because the extension ships without a lock file and its
dependencies resolve differently per TYPO3 version, there is one SBOM for each supported TYPO3
version (13 and 14).

- **Download:** open the run of the
  [SBOM workflow](https://github.com/web-vision/ai-llms-txt/actions/workflows/sbom.yml) for the
  release tag and download the artifact `sbom`. This requires a GitHub account; artifacts are
  kept for 90 days.
- **On request:** for older releases, or without a GitHub account, ask at hello@web-vision.de.

The SBOMs are also uploaded to web-vision's vulnerability management (OWASP Dependency-Track),
which checks the listed components against newly published vulnerabilities.

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
