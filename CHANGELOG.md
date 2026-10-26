# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Fixed
- The Markdown version of a page had two main headings when its content starts with an
  `<h1>`, e.g. from a header content element. The page title is now left out in that case,
  and the page description follows the heading of the content
- Top-level pages in llms.txt were listed with `+` instead of `-` as list marker, the marker
  used by the llmstxt.org specification and expected by its reference parser
- The Markdown version of a page lost all content following an `<aside>` or `<footer>`
  that contains a `<div>`: layout wrapper divs are now unwrapped as a whole instead of
  stripping every closing `</div>`
### Changed
- The Markdown (`.md`) version of a page is served as `Content-Type: text/markdown` instead
  of `text/plain`

## [0.3.0] - 2026-10-13

### Added
- Site setting `llmsTxtExcludeDoktypes` to leave additional page types out of llms.txt;
  subpages of an excluded page move up instead of being dropped (#1)
- Optional caching of the generated llms.txt with the site setting `llmsTxtCacheLifetime`
  (seconds, `0` = disabled, the default), applied to TYPO3's page cache as well. Entries are
  kept separate per frontend user group combination and cleared when a page is saved in the
  backend (#2)
- Support for TYPO3 14.3 LTS
- Errors while generating llms.txt or Markdown are logged through the TYPO3 logging framework
- `X-Content-Type-Options: nosniff` header for both outputs
- Documentation of all site settings, the CLI export command and known limitations

### Changed
- The Markdown (`.md`) version of a page now answers with "page not found" when the
  extension is disabled for the site, or when the page has "Index this page" switched off
- Page titles, descriptions and site settings rendered into llms.txt are always kept on
  a single line
- The YAML frontmatter of `ai-llms:download-markdown --include-metadata` is now valid YAML,
  and the export timestamp is in UTC
- `typo3/cms-frontend` and `typo3/cms-seo` are now declared as dependencies in
  `ext_emconf.php` as well (no change for Composer installations)

### Removed
- Support for TYPO3 12

### Fixed
- Pages of other languages appeared in the language sections of llms.txt on deep page
  trees (#10)
- Translated pages showed the default language's SEO title and description in their
  Markdown version (#7)
- Text content was lost after a text & media content element in the Markdown version (#7)
- llms.txt contained wrong `.md` links for page URLs ending with a slash (#8)
- Excluded page types were only skipped on the first level of the page tree
- Translated pages did not list their subpages in llms.txt
- Language fallback chains of the site configuration were not respected
- llms.txt needed one database query per page; queries are now batched per tree level
- llms.txt was kept in TYPO3's page cache for up to a day, so changes to pages below the
  first level did not show up; it now follows `llmsTxtCacheLifetime`
- Error responses of llms.txt and the Markdown version were stored in the page cache
- A single invalid UTF-8 byte in the page content made the whole Markdown version empty

### Security
- The Markdown version of a page was served even when the extension was disabled for the
  site, and for pages excluded from indexing
- Error messages in the Markdown output could expose internal details such as database
  queries and file system paths
- Escaped markup in page content was turned back into live HTML in the Markdown output,
  allowing stored cross-site scripting in applications that render it
- Line breaks and Markdown syntax in page titles, descriptions and site settings could
  inject arbitrary lines, such as fake links, into llms.txt
- Line breaks in page titles and descriptions could inject arbitrary keys into the YAML
  frontmatter of the CLI export

## [0.2.1] - 2026-01-22

### Changed
- Updated the PHP versions used in the CI workflows and aligned code formatting

## [0.2.0] - 2026-01-22

### Added
- Unit tests for ConfigurationService with full coverage
- TYPO3 v14 LTS support in test runner scripts
- Comprehensive test suite runnable across TYPO3 12, 13, and 14

### Changed
- Refactored ConfigurationService to use TYPO3 core request injection pattern
- Request is now injected once via `setRequest()` instead of passing to each method
- Added fallback to `$GLOBALS['TYPO3_REQUEST']` for backward compatibility
- Improved code architecture following TYPO3 ContentObjectRenderer pattern

### WIP
- Started implementation of DownloadMarkdownCommand for CLI access to Markdown export

## [0.1.9] - 2025-10-29

### Added
- Initial release of LLMS TXT Generator extension
- Complete llms.txt generation according to llmstxt.org specification
- Automatic site navigation structure inclusion with configurable depth
- Page-to-Markdown conversion for any TYPO3 page via .md suffix
- Integration with TYPO3's native content rendering pipeline
- TypoScript-based configuration for all settings
- Route enhancers for user-friendly URLs
- Comprehensive documentation following TYPO3 standards
- Support for TYPO3 v12, v13, and v14 LTS

[Unreleased]: https://github.com/web-vision/ai-llms-txt/compare/0.3.0...HEAD
[0.3.0]: https://github.com/web-vision/ai-llms-txt/compare/0.2.1...0.3.0
[0.2.1]: https://github.com/web-vision/ai-llms-txt/compare/0.2.0...0.2.1
[0.2.0]: https://github.com/web-vision/ai-llms-txt/compare/0.1.9...0.2.0
[0.1.9]: https://github.com/web-vision/ai-llms-txt/releases/tag/0.1.9
