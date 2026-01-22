.. include:: /Includes.rst.txt

=========
ChangeLog
=========

Version 0.2.0-0.2.1
=============

Release Date: 2026-01-22

Changes
-------

**Added**

* Unit tests for ConfigurationService with full coverage (24 tests)
* TYPO3 v14 LTS support in test runner scripts
* Comprehensive test suite runnable across TYPO3 12, 13, and 14

**Changed**

* Refactored ConfigurationService to use TYPO3 core request injection pattern
* Request is now injected once via ``setRequest()`` instead of passing to each method
* Added fallback to ``$GLOBALS['TYPO3_REQUEST']`` for backward compatibility
* Improved code architecture following TYPO3 ContentObjectRenderer pattern

**Removed**

* Removed DownloadMarkdownCommand from Services.yaml (command not yet implemented)

Version 0.1.8 - 0.1.9
=====================

Release Date: 2025-12-30

Latest Changes
--------------
**Fixed**
* Corrected the exclusion of specific doktypes in PageRepository to ensure proper fetching of child pages.


=========
ChangeLog
=========
Version 0.1.7
==============

Release Date: 2025-12-18

Latest Changes
--------------

**Changed**

* Update PageRepository to exclude specific doktypes and fetch their children


Release Date: 2025-10-29

Initial Release
---------------

This is the initial release of the LLMS TXT Generator extension for TYPO3 v13.

New Features
~~~~~~~~~~~~

**Core Functionality**

* Complete llms.txt generation according to the llmstxt.org specification
* Automatic site navigation structure inclusion with configurable depth
* Page-to-Markdown conversion for any TYPO3 page via .md suffix

**Configuration Options**

* TypoScript-based configuration for all settings
* Configurable navigation depth (maxDepth setting)
* Custom title and description overrides
* Keywords/topics configuration for site metadata
* Contact email specification for AI systems
* Additional information text support

**Technical Implementation**

* Built specifically for TYPO3 v13 using modern PHP 8.2+ practices
* Service-oriented architecture with dependency injection
* Proper separation of concerns with dedicated services for each responsibility
* HTML-to-Markdown conversion using league/html-to-markdown library
* Route enhancers for user-friendly URLs (.md suffix and llms.txt endpoints)

**Documentation**

* Complete documentation following TYPO3 standards
* Administrator installation and configuration guide
* Editor usage guidelines
* Developer API reference and extension guide
* Configuration examples and best practices

System Requirements
~~~~~~~~~~~~~~~~~~~

* TYPO3 CMS 13.0 or higher
* PHP 8.2 or higher
* league/html-to-markdown ^5.1 (automatically installed via Composer)

Breaking Changes
~~~~~~~~~~~~~~~~

This is the initial release, so no breaking changes apply.

Known Issues
~~~~~~~~~~~~

* Large sites (1000+ pages) may experience performance impacts during navigation generation
* Complex HTML structures may not convert perfectly to Markdown format
* Some web servers may require configuration for .well-known directory access

Migration Notes
~~~~~~~~~~~~~~~

This is a new extension, so no migration is required.

Deprecations
~~~~~~~~~~~~

No deprecations in this initial release.

Credits
~~~~~~~

* Development: web-vision GmbH
* Based on the llmstxt.org specification for AI-readable content guidelines
* Uses league/html-to-markdown for HTML-to-Markdown conversion

Installation
~~~~~~~~~~~~

Install via Composer:

.. code-block:: bash

   composer require web-vision/ai-llms-txt

After installation, the extension is ready to use with default settings. Visit ?type=1699 or ?type=1701
or ``/.well-known/llms.txt`` if you have configured RouterEnhancers, to access the generated links.

For detailed configuration options, see the Configuration section of this documentation.
