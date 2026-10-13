..  include:: /Includes.rst.txt

..  _configuration:

=============
Configuration
=============

All settings are made per site, in the site configuration. Edit them in the
backend module :guilabel:`Site Management > Sites`, tab
:guilabel:`LLMS.TXT Configuration`, or directly in
:file:`config/sites/<site-identifier>/config.yaml`.

The extension needs no TypoScript configuration of its own: the two page types
it uses are registered automatically.

..  _configuration-site:

Site settings
=============

..  code-block:: yaml
    :caption: config/sites/main/config.yaml

    llmsTxtEnabled: true
    llmsTxtTitle: 'ACME Corporation'
    llmsTxtDescription: 'Industrial products and engineering services since 1952.'
    llmsTxtKeywords: 'manufacturing, engineering, consulting'
    llmsTxtContactEmail: 'contact@example.com'
    llmsTxtAdditionalInfo: |
      ## Licensing

      Product data sheets may be quoted with attribution.
    llmsTxtMaxDepth: 2
    llmsTxtExcludeDoktypes: '3, 199'
    llmsTxtCacheLifetime: 900

..  confval:: llmsTxtEnabled
    :name: site-llmsTxtEnabled
    :type: boolean
    :default: true

    Enables the extension for this site. This switch covers **both** outputs:

    *   ``llms.txt`` returns only a short "generation is disabled" notice.
    *   Markdown (``.md``) requests are answered with the site's regular
        "page not found" response.

..  confval:: llmsTxtTitle
    :name: site-llmsTxtTitle
    :type: string
    :default: title of the site root page

    Title used as the ``# H1`` heading of ``llms.txt``.

..  confval:: llmsTxtDescription
    :name: site-llmsTxtDescription
    :type: string
    :default: description of the site root page

    Short summary rendered as the ``>`` blockquote below the title. Line breaks
    are collapsed into a single line, as the specification expects one
    blockquote line.

..  confval:: llmsTxtKeywords
    :name: site-llmsTxtKeywords
    :type: string (comma-separated)
    :default: empty

    Topics rendered as a ``**Topics:**`` line.

..  confval:: llmsTxtContactEmail
    :name: site-llmsTxtContactEmail
    :type: string
    :default: empty

    Contact address rendered as a ``**Contact:**`` line.

..  confval:: llmsTxtAdditionalInfo
    :name: site-llmsTxtAdditionalInfo
    :type: string (Markdown)
    :default: empty

    Free Markdown appended to the end of ``llms.txt``, after a horizontal rule.
    Use it for sections such as licensing terms or "optional" link lists.
    Unlike all other values, it is written as-is: whoever can edit the site
    configuration controls this part of the file.

..  confval:: llmsTxtMaxDepth
    :name: site-llmsTxtMaxDepth
    :type: integer
    :default: 2

    How many levels of the page tree below the site root are listed. Values are
    clamped to the range 1 to 10.

..  confval:: llmsTxtExcludeDoktypes
    :name: site-llmsTxtExcludeDoktypes
    :type: string (comma-separated page types)
    :default: empty

    Additional page types (``doktype`` values) to leave out of ``llms.txt``,
    for example ``3`` for external links or a custom page type.

    Folders (254), menu separators (199) and shortcuts (4) are always excluded.
    This setting can only add to that list, not remove from it.

    The *subpages* of an excluded page are not dropped: they move up and are
    listed in place of the excluded page.

..  confval:: llmsTxtCacheLifetime
    :name: site-llmsTxtCacheLifetime
    :type: integer (seconds)
    :default: 0

    How long ``llms.txt`` is cached, at most. ``0`` disables caching: the file
    is generated on every request and also kept out of TYPO3's page cache.

    With a lifetime set, ``llms.txt`` is stored in TYPO3's page cache, and the
    generated content additionally in the ``hash`` cache. Entries are kept
    separate per combination of frontend user groups, because
    access-restricted pages appear only for visitors who may see them.

    Cache entries are tagged ``pages``, so saving any page record in the
    backend clears them. Changes that do not go through the backend, such as
    a page's start or stop time passing or a direct database import, are
    picked up once the entry expires or when the caches are flushed.

    This setting applies to ``llms.txt`` only. The Markdown version of a page
    is cached by TYPO3's page cache like the page itself.

..  _configuration-pages:

What is listed in llms.txt
==========================

A page appears in ``llms.txt`` only if all of the following apply:

*   it is visible: not hidden, not deleted, within its start and stop time;
*   it is accessible to the visitor, if it has access restrictions;
*   :guilabel:`Page > Behaviour > Miscellaneous > Hide in Menus` (``nav_hide``)
    is not set;
*   :guilabel:`SEO > Robot Instructions > Index this page` is enabled
    (``no_index`` is not set);
*   its page type is not excluded (see :confval:`site-llmsTxtExcludeDoktypes`);
*   it is within :confval:`site-llmsTxtMaxDepth`.

The Markdown version of a page (``.md``) follows the same visibility and access
rules. Pages with ``no_index`` set are answered with "page not found" there as
well, so that a page kept out of search engines is not offered to language
models instead.

..  _configuration-routes:

Route configuration
===================

Without route configuration the outputs are available through page type
parameters only: ``/?type=1699`` for ``llms.txt`` and ``/any-page?type=1701``
for Markdown. Import the bundled route enhancer to get the usual URLs:

..  code-block:: yaml
    :caption: config/sites/main/config.yaml

    imports:
      -
        resource: 'EXT:ai_llms_txt/Configuration/Routes/RouterEnhancer.yaml'

It maps the following suffixes:

..  code-block:: yaml
    :caption: EXT:ai_llms_txt/Configuration/Routes/RouterEnhancer.yaml

    routeEnhancers:
      PageTypeSuffix:
        type: PageType
        map:
          llms.txt: 1699
          .well-known/llms.txt: 1699
          .md: 1701

..  note::
    If your site already defines a ``PageType`` enhancer, for example for an
    XML sitemap, add the three ``map`` entries above to that enhancer instead
    of importing the file, so that a single enhancer handles all suffixes.

..  _configuration-typoscript:

Page types
==========

The extension registers these page types through TypoScript that is loaded
automatically. They are listed here for reference: do not copy them into your
own TypoScript.

=================  =======  ========================================
Page object        typeNum  Output
=================  =======  ========================================
``llmstxt``        1699     ``llms.txt``
``markdown_page``  1701     Markdown version of the current page
=================  =======  ========================================

Both are served as ``Content-Type: text/plain; charset=utf-8`` together with
``X-Content-Type-Options: nosniff``, so that browsers never interpret the
editor-controlled content as HTML.
