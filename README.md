# LLMS TXT Generator for TYPO3

[![TYPO3 13](https://img.shields.io/badge/TYPO3-13-orange.svg)](https://get.typo3.org/version/13)
[![PHP 8.2+](https://img.shields.io/badge/PHP-8.2+-blue.svg)](https://www.php.net/)
[![License: GPL v2+](https://img.shields.io/badge/License-GPL%20v2+-blue.svg)](https://www.gnu.org/licenses/gpl-2.0)

TYPO3 extension for generating `llms.txt` links according to the [llmstxt.org specification](https://llmstxt.org/) to control Large Language Model crawling policies.

## Features

- **Automatic llms.txt generation** - Creates policy files according to the official specification
- **Site navigation structure** - Includes your site's navigation hierarchy in the llms.txt file
- **Configurable metadata** - Add topics, contact information, and custom descriptions
- **Markdown export** - Convert any TYPO3 page to Markdown format via `.md` suffix
- **TYPO3 v13 compatibility** - Built specifically for TYPO3 v13 using modern PHP practices

## What is llms.txt?

llms.txt is an emerging standard for websites to communicate with Large Language Models and AI systems. Similar to robots.txt for web crawlers, llms.txt files provide:

- **Crawling policies** - Guidelines for AI systems on how to interact with your content
- **Site structure** - Navigation and content organization information
- **Metadata** - Topics, contact information, and site descriptions
- **Content access** - Direct links to machine-readable content formats

## Installation

### Composer (Recommended)

```bash
composer require web-vision/ai-llms-txt
```

## Quick Start

After installation, the extension works immediately with default settings:

- **llms.txt generation**: Visit `https://yoursite.com/?type=1699`
- **Markdown pages**: Visit `https://yoursite.com/?type=1701`

with Route Enhancer:

- **llms.txt file**: Visit `https://yoursite.com/.well-known/llms.txt`
- **Markdown pages**: Add `.md` to any page URL (e.g., `https://yoursite.com/about.md`)

## Configuration

All settings can be configured in your site configuration.


## Route Configuration

To enable user-friendly URLs, include the route enhancers in your site configuration:

```yaml
# config/sites/main/config.yaml
imports:
  -
    resource: 'EXT:ai_llms_txt/Configuration/Routes/RouterEnhancer.yaml'
```

This enables:
- `.md` suffix for Markdown content
- `llms.txt` for direct access to the specification file

## Usage Examples

### Accessing Generated Content

**llms.txt files:**
- `https://yoursite.com/?type=1699`
- `https://yoursite.com/.well-known/llms.txt` - with route enhancer
- `https://yoursite.com/llms.txt` - Alternative access (with route enhancer)

**Markdown content:**
- `https://yoursite.com/?type=1701`
- `https://yoursite.com/about.md` - Markdown version of your About page
- `https://yoursite.com/services/consulting.md` - Markdown version of any page


## Requirements

- **TYPO3**: 13.0 or higher
- **PHP**: 8.2 or higher
- **Dependencies**: league/html-to-markdown (automatically installed)

## Documentation

Comprehensive documentation is available covering:

- [Installation and Configuration](Documentation/Administrator/Index.rst)
- [Editor Guidelines](Documentation/Editor/Index.rst)
- [Developer API Reference](Documentation/Developer/Index.rst)
- [TypoScript Configuration](Documentation/Configuration/Index.rst)

## Contributing

Contributions are welcome! Please:

1. Follow TYPO3 coding standards
2. Include tests for new features
3. Update documentation for configuration changes
4. Use dependency injection patterns
5. Maintain strict typing

## License

This extension is licensed under GPL v2+ - see the [LICENSE](LICENSE) file for details.

## Support

- **Documentation**: Full documentation in the `Documentation/` folder
- **Issues**: Report issues via the project issue tracker
- **Community**: Join TYPO3 community discussions for general TYPO3 support

## Related

- [llmstxt.org](https://llmstxt.org/) - Official specification
- [TYPO3 Documentation](https://docs.typo3.org/) - TYPO3 CMS documentation

## Supported Versions

| Version | Supported          | End of Support |
|---------|--------------------|----------------|
| 0.x     | :white_check_mark: | 2029-06-30     |

## Security

Found a vulnerability? Please report it privately via our
[security report form](https://security.web-vision.de) — **do not** open a public issue.
See [SECURITY.md](SECURITY.md) for the full vulnerability disclosure policy,
including what to expect and our safe harbor statement.

## Simplified EU Declaration of Conformity (Annex VI)

> Hereby, web-vision GmbH declares that the product with digital elements
> type LLMS TXT Generator is in compliance with Regulation (EU) 2024/2847.
>
> The full text of the EU declaration of conformity is available at the
> following internet address:
> https://security.web-vision.de/conformity/web-vision/ai-llms-txt/0.3.0/en/

The full declarations are also included in this repository:
[English](EU-Declaration-of-Conformity.md) ·
[Deutsch](EU-Konformitaetserklaerung.md).
