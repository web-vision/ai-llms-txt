..  include:: /Includes.rst.txt

..  _administrator:

=============
Administrator
=============

..  _installation:

Installation
============

Install the extension with Composer:

..  code-block:: bash

    composer require web-vision/ai-llms-txt

Then set up the extension, so that the TYPO3 caches are flushed:

..  code-block:: bash

    vendor/bin/typo3 extension:setup

The extension is also available in the TYPO3 Extension Repository (TER) under
the key ``ai_llms_txt``. Classic mode installations are not covered by the
automated tests.

..  _setup:

Setup
=====

The extension works without further configuration: it registers its page
types automatically and is enabled for every site.

1.  Import the route enhancer into each site configuration to get the
    ``/llms.txt`` and ``.md`` URLs, see :ref:`configuration-routes`.
2.  Optionally adjust the title, summary and other values in the site
    configuration, see :ref:`configuration-site`.
3.  To keep a site out of it entirely, disable
    :confval:`site-llmsTxtEnabled` for that site.

..  _urls:

Available URLs
==============

..  list-table::
    :header-rows: 1
    :widths: 55 45

    *   - URL
        - Output
    *   - ``https://example.com/llms.txt``
        - ``llms.txt`` (with route enhancer)
    *   - ``https://example.com/.well-known/llms.txt``
        - ``llms.txt`` (with route enhancer)
    *   - ``https://example.com/about.md``
        - Markdown version of ``/about`` (with route enhancer)
    *   - ``https://example.com/?type=1699``
        - ``llms.txt`` (always available)
    *   - ``https://example.com/about?type=1701``
        - Markdown version of ``/about`` (always available)

..  _testing-installation:

Testing the installation
========================

1.  Open ``https://example.com/llms.txt``. It starts with the site title as
    a ``#`` heading, followed by the summary and the list of pages.
2.  Append ``.md`` to the URL of any regular page, for example
    ``https://example.com/about.md``. The page content is shown as plain
    Markdown.

..  _cli-export:

Exporting pages as Markdown files
=================================

The command ``ai-llms:download-markdown`` saves the Markdown version of pages
as ``.md`` files, for example to feed them into an AI knowledge base:

..  code-block:: bash

    # Export all sites to var/markdown-export/
    vendor/bin/typo3 ai-llms:download-markdown

    # Export one site, with YAML frontmatter, and preview first
    vendor/bin/typo3 ai-llms:download-markdown main --include-metadata --dry-run

..  list-table::
    :header-rows: 1
    :widths: 30 70

    *   - Argument / option
        - Description
    *   - ``siteIdentifier``
        - Site to export. Without it, all sites are exported.
    *   - ``-o``, ``--output-dir``
        - Target directory, relative to the project root.
          Default: ``var/markdown-export``.
    *   - ``-l``, ``--limit``
        - Maximum number of pages to export. Default: ``0`` (no limit).
    *   - ``-f``, ``--format``
        - File naming: ``slug`` (default), ``uid`` or ``title``.
    *   - ``-m``, ``--include-metadata``
        - Prepend YAML frontmatter with uid, title, description, URL, site
          and export time.
    *   - ``--flat``
        - Write all files into one directory instead of one per site.
    *   - ``--overwrite``
        - Overwrite existing files.
    *   - ``--dry-run``
        - List what would be exported without writing files.

The command fetches each page's ``.md`` URL over HTTP, so the site must be
reachable from the server under its configured base URL, and the route
enhancer must be imported.

..  _troubleshooting:

Troubleshooting
===============

``/llms.txt`` returns "page not found"
    The route enhancer is not imported into the site configuration, see
    :ref:`configuration-routes`. ``/?type=1699`` works without any routing
    and helps to tell a routing problem apart from other causes.

``/.well-known/llms.txt`` is not reachable
    Some web server configurations block paths starting with a dot. Allow
    ``/.well-known/`` to be passed to TYPO3, or use ``/llms.txt``.

A page's ``.md`` URL returns "page not found"
    This is intended when the extension is disabled for the site, or when
    the page has :guilabel:`Index this page` switched off (``no_index``).

A page is missing from ``llms.txt``
    Check the conditions in :ref:`configuration-pages`. If caching is
    enabled and the page was changed outside the backend, for example by its
    start time passing, it appears once the cache entry expires.

``llms.txt`` contains an "error: Failed to generate content" line
    Generation failed. The cause is logged through the TYPO3 logging
    framework (by default in :file:`var/log/`), not shown in the response.

..  _performance:

Performance
===========

``llms.txt`` is generated with batched database queries per tree level rather
than one query per page. On large sites, keep
:confval:`site-llmsTxtMaxDepth` low and set :confval:`site-llmsTxtCacheLifetime`
so the file is not rebuilt on every request.

The Markdown version of a page renders the full page through TYPO3 and
converts it, so it takes about as long as rendering the HTML page.
