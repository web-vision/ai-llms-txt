..  include:: /Includes.rst.txt

..  _known-problems:

==============
Known problems
==============

Please report new problems at
https://github.com/web-vision/ai-llms-txt/issues. Report security issues
privately as described in :file:`SECURITY.md` instead.

..  _known-problems-languages:

``llms.txt`` lists the default language only
============================================

On multi-language sites, ``llms.txt`` always lists the page tree of the site's
default language, also when it is requested under a language prefix such as
``/de/llms.txt``. Translated pages are still available as Markdown through
their own ``.md`` URLs, but are not listed.

..  _known-problems-disabled:

A disabled site still answers ``/llms.txt``
===========================================

When :confval:`site-llmsTxtEnabled` is off, ``/llms.txt`` returns a short
"generation is disabled" notice with HTTP status 200, while ``.md`` requests
return "page not found".

..  _known-problems-markdown:

Markdown conversion
===================

The Markdown version is converted from the rendered HTML of the page's
content, so its quality depends on the markup your templates produce:

*   Tables are not converted and lose their structure
    (`#11 <https://github.com/web-vision/ai-llms-txt/issues/11>`__).
*   Layout markup is removed with pattern matching, not a full HTML parser.
    All ``<div>`` elements are unwrapped; with unusually nested markup this
    can occasionally move or merge text.
*   Images, video, audio and embedded frames are removed; links are kept.
*   Forms, interactive widgets and content rendered by JavaScript have no
    meaningful Markdown representation.
*   Only content elements of the page are included, from all column
    positions, ordered by column and then by their position in the column.
    Content that the page template renders by itself, such as menus or
    footer content, is not included.

The result is meant to be a readable, semantic version of the content, not a
faithful copy of the page's layout.
