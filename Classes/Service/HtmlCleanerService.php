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
     * Elements that start a block of their own. Whitespace next to them is only layout of
     * the HTML source; next to any other element it separates words.
     */
    private const BLOCK_ELEMENTS = 'address|article|aside|blockquote|br|caption|dd|details|div|dl|dt|fieldset'
        . '|figcaption|figure|footer|form|h[1-6]|header|hr|legend|li|main|nav|ol|p|pre|section|summary'
        . '|table|tbody|td|tfoot|th|thead|tr|ul';

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

        // In preformatted text, such as code, line breaks and indentation are content.
        // It is set aside until the whitespace of the rest has been normalized.
        $preformatted = [];
        $placeholderPrefix = 'ai-llms-txt-pre-' . bin2hex(random_bytes(8)) . '-';
        $html = preg_replace_callback(
            '/<pre\b[^>]*>.*?<\/pre>/is',
            static function (array $matches) use (&$preformatted, $placeholderPrefix): string {
                $preformatted[] = $matches[0];
                return '<!--' . $placeholderPrefix . (count($preformatted) - 1) . '-->';
            },
            $html
        ) ?? $html;
        $placeholderPattern = '<!--' . preg_quote($placeholderPrefix, '/') . '(\d+)-->';

        // Step 1: Remove empty elements (run multiple times for nested elements)
        for ($i = 0; $i < 3; $i++) {
            $html = $this->replace('/<a[^>]*>\s*<\/a>/i', '', $html);
            $html = $this->replace('/<div[^>]*>\s*<\/div>/i', '', $html);
            $html = $this->replace('/<span[^>]*>\s*<\/span>/i', '', $html);
            $html = $this->replace('/<p(?:\s[^>]*)?>(?:\s|&nbsp;|&#160;)*<\/p>/i', '', $html);
            $html = $this->replace('/<(section|article|aside)[^>]*>\s*<\/\1>/i', '', $html);
        }

        // Step 2: Normalize whitespace
        $html = $this->replace('/\s+/u', ' ', $html);
        $html = $this->replace(
            '/\s*(<\/?(?:' . self::BLOCK_ELEMENTS . ')(?:\s[^>]*)?>|' . $placeholderPattern . ')\s*/i',
            '$1',
            $html
        );

        // Step 3: Remove common layout/column wrapper divs that create empty lines
        $html = $this->unwrapLayoutDivs($html);

        if ($preformatted !== []) {
            $html = preg_replace_callback(
                '/' . $placeholderPattern . '/',
                static fn (array $matches): string => $preformatted[(int)$matches[1]] ?? '',
                $html
            ) ?? $html;
        }

        return trim($html);
    }

    /**
     * Replace layout/column wrapper divs by their children.
     *
     * Opening and closing tags are always removed together. Removing every closing </div>
     * while keeping all other opening <div> tags leaves unclosed elements, which makes the
     * HTML parser of the Markdown converter nest the rest of the page into e.g. an <aside>
     * that is then dropped completely via "remove_nodes" - truncating the Markdown output.
     */
    private function unwrapLayoutDivs(string $html): string
    {
        if (trim($html) === '') {
            return $html;
        }

        $previousUseInternalErrors = libxml_use_internal_errors(true);
        $document = new \DOMDocument();
        $loaded = $document->loadHTML(
            '<?xml encoding="UTF-8"><div id="ai-llms-txt-root">' . $html . '</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previousUseInternalErrors);

        $root = $loaded ? $document->getElementById('ai-llms-txt-root') : null;
        $divs = $root !== null ? (new \DOMXPath($document))->query('.//div[@class]', $root) : false;
        if ($root === null || $divs === false) {
            return $html;
        }

        foreach ($divs as $div) {
            $parent = $div->parentNode;
            if (!$div instanceof \DOMElement
                || $parent === null
                || preg_match('/container|row|col|column|grid|layout|wrapper/i', $div->getAttribute('class')) !== 1
            ) {
                continue;
            }
            while ($div->firstChild !== null) {
                $parent->insertBefore($div->firstChild, $div);
            }
            $parent->removeChild($div);
        }

        $result = '';
        foreach ($root->childNodes as $childNode) {
            $result .= $document->saveHTML($childNode);
        }

        return $result;
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
