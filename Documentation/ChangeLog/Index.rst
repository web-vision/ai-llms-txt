..  include:: /Includes.rst.txt

..  _changelog:

=========
ChangeLog
=========

The complete history is kept in
`CHANGELOG.md <https://github.com/web-vision/ai-llms-txt/blob/main/CHANGELOG.md>`__.

..  _changelog-unreleased:

Unreleased
==========

..  important::

    *   The ``0.2`` branch supports TYPO3 12.4 LTS only. It is not released
        and not officially supported. Sites on TYPO3 13.4 or 14.3 use version
        0.5 or later.
    *   The branch brings the fixes and features of 0.3.0, 0.4.0 and 0.5.0 to
        TYPO3 12, including their changes in behaviour. Check them before
        updating.
    *   The Markdown version of a page now answers with "page not found" when
        the extension is disabled for the site, or when the page has
        :guilabel:`Index this page` switched off.
    *   The Markdown version of a page is served as
        ``Content-Type: text/markdown`` instead of ``text/plain``. Check
        proxies, CDN rules or clients that match on the content type.
    *   If the content of a page starts with an ``<h1>``, the Markdown version
        no longer adds the page title as a second main heading.

**Added**

*   Site setting :confval:`site-llmsTxtExcludeDoktypes` to leave additional
    page types out of ``llms.txt`` (#1)
*   Optional caching with :confval:`site-llmsTxtCacheLifetime`, disabled by
    default (#2)
*   Logging of generation errors, ``X-Content-Type-Options: nosniff`` header
*   Documentation of all settings, the CLI export and known limitations
*   README sections on security updates, uninstallation, automatic updates,
    notes for integrators and the SBOM (#16)

**Fixed**

*   Pages of other languages appeared in the language sections of
    ``llms.txt`` (#10)
*   Translated SEO title and description, and text after text & media
    elements, in the Markdown version (#7)
*   Wrong ``.md`` links for page URLs ending with a slash (#8)
*   Excluded page types, translated subpages and language fallback handling
    in ``llms.txt``
*   Performance on large page trees: queries are batched per tree level
*   ``llms.txt`` stayed in TYPO3's page cache for up to a day; it now follows
    :confval:`site-llmsTxtCacheLifetime`, and error responses are not cached
*   Top-level pages in ``llms.txt`` were listed with ``+`` instead of ``-``
*   Content following an ``<aside>`` or ``<footer>`` containing a ``<div>``
    was lost in the Markdown version

**Security**

*   The Markdown version ignored the site's enable switch and the page's
    "no index" flag
*   Error messages in the Markdown output could expose internal details
*   Escaped markup in page content became live HTML in the Markdown output
*   Line breaks and Markdown syntax in titles, descriptions and settings
    could inject lines into ``llms.txt`` and keys into the CLI export's
    YAML frontmatter

..  _changelog-0-2-2:

0.2.2 - 2026-10-30
==================

*   The Markdown version of a page had the content elements of a
    ``b13/container`` grid twice: inside the container, and once more on their
    own. They are now only rendered by their container

..  _changelog-0-2-1:

0.2.1 - 2026-01-22
==================

*   Updated the PHP versions used in the CI workflows and aligned code
    formatting

..  _changelog-0-2-0:

0.2.0 - 2026-01-22
==================

*   Refactored :php:`ConfigurationService` to receive the request once via
    :php:`setRequest()`
*   Unit tests for :php:`ConfigurationService`
*   TYPO3 14 support in the test runner

..  _changelog-0-1-9:

0.1.9 - 2025-12-30
==================

*   Fixed the exclusion of folders, separators and shortcuts so that their
    subpages are still listed

..  _changelog-0-1-7:

0.1.7 - 2025-12-18
==================

*   Folders, separators and shortcuts are excluded from ``llms.txt``, their
    subpages are listed instead

..  _changelog-initial:

Initial release - 2025-10-29
============================

*   ``llms.txt`` generation according to the llmstxt.org specification, with
    the site's page tree up to a configurable depth
*   Markdown version of every page via the ``.md`` suffix
*   Route enhancers for ``/llms.txt``, ``/.well-known/llms.txt`` and ``.md``
