..  include:: /Includes.rst.txt

..  _changelog:

=========
ChangeLog
=========

The complete history is kept in
`CHANGELOG.md <https://github.com/web-vision/ai-llms-txt/blob/main/CHANGELOG.md>`__.

..  _changelog-0-5-0:

0.5.0 - 2026-11-11
==================

..  important::

    *   The ``.md`` links in ``llms.txt`` and the link to ``llms.txt`` in the
        page head are built by the router. Without the route enhancer they now
        use ``?type=1701`` and ``?type=1699``, which work, instead of URLs
        that did not resolve.
    *   ``ai-llms:download-markdown`` exports the whole page tree, names files
        after the page's URL segment by default, and ends with a non-zero exit
        code when a page fails. Check scripts that process its output.
    *   Tables become Markdown tables in the Markdown version, and ``llms.txt``
        no longer escapes periods, hyphens or parentheses.
    *   league/html-to-markdown 5.2.3 or later is required.

**Added**

*   Tables are converted to Markdown tables (#11)

**Fixed**

*   ``.md`` links in ``llms.txt`` for sites whose page URLs end in ``.html``
    or that do not import the route enhancer
*   Missing spaces between inline elements, and code blocks collapsed onto
    one line, in the Markdown version
*   Paragraphs such as "SPSS" were dropped from the Markdown version
*   The description of top-level pages was missing in ``llms.txt``
*   Subpages of an excluded page type below the first level were listed out
    of order
*   The CLI export only exported the first level of pages; ``--format=slug``,
    ``--dry-run``, unknown sites and the exit code did not work as documented
*   The link to ``llms.txt`` in the page head ignored the site's base path
    and was also added for disabled sites
*   A warning for untranslated pages of a fallback language in the
    language-aware page queries
*   The content elements of a ``b13/container`` grid appeared twice in the
    Markdown version
*   Content that is not cacheable, such as ``USER_INT`` objects or plugins
    with filters, was missing from the Markdown version. It is now rendered,
    and a page with such content is no longer kept in TYPO3's page cache

**Security**

*   league/html-to-markdown 5.2.3 is required, which fixes code, links and
    attributes breaking out of their Markdown, and quadratic conversion time

..  _changelog-0-4-0:

0.4.0 - 2026-10-26
==================

..  important::

    *   The Markdown version of a page is served as
        ``Content-Type: text/markdown`` instead of ``text/plain``. Check
        proxies, CDN rules or clients that match on the content type.
    *   If the content of a page starts with an ``<h1>``, the Markdown version
        no longer adds the page title as a second main heading.

**Added**

*   Support for PHP 8.5 (#17)
*   README sections on security updates, uninstallation, automatic updates,
    notes for integrators and the SBOM (#16)

**Fixed**

*   Second main heading in the Markdown version when the content starts with
    an ``<h1>``
*   Top-level pages in ``llms.txt`` were listed with ``+`` instead of ``-``
*   Content following an ``<aside>`` or ``<footer>`` containing a ``<div>``
    was lost in the Markdown version

..  _changelog-0-3-0:

0.3.0 - 2026-10-13
==================

..  important::

    *   TYPO3 12 is no longer supported. The extension requires TYPO3 13.4 LTS
        or 14.3 LTS.
    *   The Markdown version of a page now answers with "page not found" when
        the extension is disabled for the site, or when the page has
        :guilabel:`Index this page` switched off.

**Added**

*   Site setting :confval:`site-llmsTxtExcludeDoktypes` to leave additional
    page types out of ``llms.txt`` (#1)
*   Optional caching with :confval:`site-llmsTxtCacheLifetime`, disabled by
    default (#2)
*   Support for TYPO3 14.3 LTS
*   Logging of generation errors, ``X-Content-Type-Options: nosniff`` header
*   Documentation of all settings, the CLI export and known limitations

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

**Security**

*   The Markdown version ignored the site's enable switch and the page's
    "no index" flag
*   Error messages in the Markdown output could expose internal details
*   Escaped markup in page content became live HTML in the Markdown output
*   Line breaks and Markdown syntax in titles, descriptions and settings
    could inject lines into ``llms.txt`` and keys into the CLI export's
    YAML frontmatter

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
