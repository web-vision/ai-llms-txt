..  include:: /Includes.rst.txt

..  _introduction:

============
Introduction
============

What does this extension do?
============================

The LLMS TXT Generator gives Large Language Models (LLMs) and AI tools a
compact, machine-readable view of a TYPO3 site, following the
`llmstxt.org specification <https://llmstxt.org/>`__.

It provides two outputs:

1.  **llms.txt** - a Markdown index of the site: its title, a short summary,
    optional metadata and a structured list of links to the site's pages.
2.  **Markdown pages** - every listed page can be fetched as plain Markdown by
    appending ``.md`` to its URL, without navigation, header or footer.

Key features
============

*   **llms.txt generation** at ``/llms.txt`` and ``/.well-known/llms.txt``
*   **Page tree listing** with configurable depth and page type exclusion
*   **Markdown version of every page** through TYPO3's regular rendering, so
    all content elements and column positions are included
*   **Per-site configuration** of title, summary, topics, contact address and
    additional content
*   **Respects page visibility**: hidden, access-restricted, "hide in menu" and
    "no index" pages are not listed
*   **Optional caching** of the generated ``llms.txt``
*   **CLI export** of pages as Markdown files

What is llms.txt?
=================

``llms.txt`` is a proposed convention for a Markdown file at the root of a
website that tells language models what the site is about and where its most
useful content is. Language models have limited context windows and struggle
with full HTML pages; a curated index with links to clean Markdown versions
lets them find and read the relevant content directly.

``llms.txt`` is **not** an access control mechanism. It does not grant or deny
permission to crawl or train on your content; use ``robots.txt`` and your
server configuration for that.

Requirements
============

*   TYPO3 12.4 LTS
*   PHP 8.2, 8.3 or 8.4
*   The TYPO3 system extension ``seo`` (installed automatically with Composer)

The ``0.2`` branch keeps projects on TYPO3 12 running. It is not released and
not officially supported: the extension is supported for TYPO3 13.4 LTS and
14.3 LTS with version 0.5 or later.
