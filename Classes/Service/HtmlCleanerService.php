<?php

declare(strict_types=1);

namespace WebVision\AiLlmsTxt\Service;

/**
 * Service for cleaning TYPO3 HTML output
 * Removes framework-specific markup and presentational elements
 */
class HtmlCleanerService
{
    /**
     * Clean up TYPO3-specific HTML before conversion to Markdown
     *
     * Removes common TYPO3 wrapper elements and presentational markup
     * while preserving semantic HTML structure (headings, links, lists, paragraphs).
     */
    public function cleanTypo3Html(string $html): string
    {
        // The /u patterns below return null on malformed UTF-8, which would otherwise
        // cascade through the remaining replacements and blank the entire document.
        // Scrub invalid byte sequences instead, so a single bad byte in one content
        // element cannot cost the whole page its Markdown representation.
        if (!mb_check_encoding($html, 'UTF-8')) {
            $html = mb_convert_encoding($html, 'UTF-8', 'UTF-8');
        }

        // Step 1: Remove empty elements (run multiple times for nested elements)
        for ($i = 0; $i < 3; $i++) {
            $html = $this->replace('/<a[^>]*>\s*<\/a>/i', '', $html);
            $html = $this->replace('/<div[^>]*>\s*<\/div>/i', '', $html);
            $html = $this->replace('/<span[^>]*>\s*<\/span>/i', '', $html);
            $html = $this->replace('/<p[^>]*>[\s\r\n&nbsp;]*<\/p>/i', '', $html);
            $html = $this->replace('/<(section|article|aside)[^>]*>\s*<\/\1>/i', '', $html);
        }

        // Step 2: Normalize whitespace
        $html = $this->replace('/\s+/u', ' ', $html);
        $html = $this->replace('/>\s+</u', '><', $html);
        $html = $this->replace('/\s*(<\/?(?:h[1-6]|p|ul|ol|li|blockquote|pre|table|tr|td|th)(?:\s[^>]*)?>)\s*/i', '$1', $html);

        // Step 3: Remove common layout/column wrapper divs that create empty lines
        $html = $this->replace('/<div[^>]*class="[^"]*(?:container|row|col|column|grid|layout|wrapper)[^"]*"[^>]*>/i', '', $html);
        $html = $this->replace('/<\/div>/i', '', $html);

        return trim($html);
    }

    /**
     * Apply a single replacement, keeping the input unchanged if PCRE fails.
     *
     * preg_replace() returns null on failure (hitting pcre.backtrack_limit on a large
     * page, or malformed UTF-8 reaching a /u pattern). Returning the unmodified subject
     * degrades to "slightly untidy HTML" instead of silently discarding all content,
     * and avoids passing null into the next string operation - a deprecation today and
     * a TypeError under PHP 9.
     */
    private function replace(string $pattern, string $replacement, string $subject): string
    {
        return preg_replace($pattern, $replacement, $subject) ?? $subject;
    }
}
