..  include:: /Includes.rst.txt

..  _developer:

=========
Developer
=========

..  _architecture:

Architecture
============

Both outputs are TypoScript page objects that call a ``USER`` function on
:php:`\WebVision\AiLlmsTxt\Controller\LlmsTxtController`:

``llms.txt`` (typeNum 1699)
    :php:`generateAction()` delegates to :php:`LlmsTxtGeneratorService`, which
    reads the site settings through :php:`ConfigurationService`, builds the
    page tree with :php:`NavigationBuilder` and caches the result if a cache
    lifetime is configured.

Markdown (typeNum 1701)
    :php:`renderPageAsMarkdown()` checks that Markdown output is allowed for
    the site and page, renders the page's content through TYPO3's
    :php:`ContentObjectRenderer`, strips layout markup with
    :php:`HtmlCleanerService` and converts the result with
    :php:`MarkdownConverterService` (based on ``league/html-to-markdown``).

..  list-table::
    :header-rows: 1
    :widths: 40 60

    *   - Class (``WebVision\AiLlmsTxt\…``)
        - Responsibility
    *   - ``Controller\LlmsTxtController``
        - Entry points for both page types
    *   - ``Service\LlmsTxtGeneratorService``
        - Assembles ``llms.txt`` and manages its cache
    *   - ``Service\ConfigurationService``
        - Typed access to the site settings
    *   - ``Builder\NavigationBuilder``
        - Builds and formats the page tree, with language fallback
    *   - ``Repository\PageRepository``
        - Page queries with frontend restrictions, batched per tree level
    *   - ``Service\UrlGeneratorService``
        - Absolute frontend URLs for page records
    *   - ``Service\HtmlCleanerService``
        - Removes empty and layout-only markup before conversion
    *   - ``Service\MarkdownConverterService``
        - HTML to Markdown conversion
    *   - ``Command\DownloadMarkdownCommand``
        - CLI command ``ai-llms:download-markdown``

..  _api:

Public API and backward compatibility
=====================================

The extension follows `Semantic Versioning <https://semver.org/>`__. The
following are covered by it and change incompatibly only in a new major
version:

*   the site configuration settings (:ref:`configuration-site`),
*   the page types 1699 and 1701 and the bundled route enhancer,
*   the structure of the ``llms.txt`` and Markdown output,
*   the CLI command ``ai-llms:download-markdown`` and its options.

The PHP classes are internal. They are not marked ``final`` so that projects
can replace a service through dependency injection, but their methods may
change in minor versions. If you need a stable hook, please open an issue
describing your use case.

..  _testing:

Testing
=======

Tests run in containers through :file:`Build/Scripts/runTests.sh` (Docker or
Podman). Install the dependencies for the TYPO3 version to test first:

..  code-block:: bash

    # Install dependencies for TYPO3 13 (or 14)
    Build/Scripts/runTests.sh -t 13 -s composerUpdate

    # Unit and functional tests
    Build/Scripts/runTests.sh -t 13 -p 8.2 -s unit
    Build/Scripts/runTests.sh -t 13 -p 8.2 -s functional -d sqlite

    # Static analysis and coding guidelines
    Build/Scripts/runTests.sh -t 13 -s phpstan
    Build/Scripts/runTests.sh -s cgl -n

Run ``Build/Scripts/runTests.sh -h`` for all options. The same suites run on
GitHub Actions for TYPO3 13 and 14 with PHP 8.2 to 8.4.

..  _contributing:

Contributing
============

Contributions are welcome at https://github.com/web-vision/ai-llms-txt.

*   Follow the TYPO3 coding guidelines: ``Build/Scripts/runTests.sh -s cgl``
    fixes most issues automatically.
*   Add tests for new behaviour and bug fixes. Test methods use the
    ``#[Test]`` attribute instead of a ``test`` prefix.
*   Document new settings in :ref:`configuration-site`.
*   Use ``declare(strict_types=1)``, full type declarations and constructor
    injection.

Please report security issues privately as described in :file:`SECURITY.md`,
not in the public issue tracker.
