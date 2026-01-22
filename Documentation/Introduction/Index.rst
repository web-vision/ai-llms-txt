.. include:: /Includes.rst.txt

============
Introduction
============

What does this extension do?
============================

The LLMS TXT Generator extension provides TYPO3 with the capability to generate machine-readable files according to the `llmstxt.org specification <https://llmstxt.org/>`__.

The extension creates two types of content:

1. **llms.txt files** - Machine-readable policy files that inform Large Language Models (LLMs) and AI crawlers about your site's crawling preferences and content structure
2. **Markdown content** - Human and machine-readable representations of your TYPO3 pages in Markdown format

Key Features
============

* **Automatic llms.txt generation** - Creates policy links at /.well-known/llms.txt according to the official specification
* **Site navigation structure** - Automatically includes your site's navigation hierarchy in the llms.txt file
* **Configurable metadata** - Add topics, contact information, and custom descriptions
* **Markdown export** - Convert any TYPO3 page to Markdown format via .md suffix
* **TYPO3 v12, v13 & v14 compatibility** - Supports all current LTS versions using modern PHP practices
* **Flexible configuration** - Control depth, content, and behavior through Site Configuration
* **Frontend rendering integration** - Leverages TYPO3's native content rendering pipeline

What is llms.txt?
=================

llms.txt is an emerging standard for websites to communicate with Large Language Models and AI systems. Similar to robots.txt for web crawlers, llms.txt files provide:

* **Crawling policies** - Guidelines for AI systems on how to interact with your content
* **Site structure** - Navigation and content organization information
* **Metadata** - Topics, contact information, and site descriptions
* **Content access** - Direct links to machine-readable content formats

The specification follows a simple, human-readable format that both humans and AI systems can easily parse.

Use Cases
=========

**Educational Institutions**
  Provide structured access to course catalogs, faculty information, and academic content for AI-powered educational tools.

**Content Publishers**
  Offer clear guidelines for AI systems accessing articles, documentation, and media content.

**Business Websites**
  Structure company information, services, and contact details for AI-powered business discovery tools.

**Documentation Sites**
  Enable AI systems to better understand and reference technical documentation and knowledge bases.

Requirements
============

* TYPO3 CMS 13.0 or higher
* PHP 8.2 or higher
* league/html-to-markdown package (automatically installed)

The extension integrates seamlessly with existing TYPO3 installations and requires minimal configuration to get started.
