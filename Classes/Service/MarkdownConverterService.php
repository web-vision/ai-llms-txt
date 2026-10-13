<?php

declare(strict_types=1);

namespace WebVision\AiLlmsTxt\Service;

use League\HTMLToMarkdown\HtmlConverter;

/**
 * Service for converting HTML to Markdown
 * Handles HTML to Markdown conversion with proper cleaning
 */
class MarkdownConverterService
{
    public function __construct(
        private readonly HtmlCleanerService $htmlCleanerService
    ) {
    }

    /**
     * Convert HTML content to clean Markdown
     * Strips images and media but preserves links for SEO
     */
    public function convertHtmlToMarkdown(string $html): string
    {
        if (empty($html)) {
            return '';
        }

        $html = $this->htmlCleanerService->cleanTypo3Html($html);

        $converter = new HtmlConverter([
            'strip_tags' => true,
            'remove_nodes' => 'img picture source video audio iframe script style footer aside',
            'preserve_comments' => false,
            'hard_break' => true,
            'strip_placeholder_links' => false,
            'use_autolinks' => false,
            'header_style' => 'atx', // Use ATX style headers (e.g., ## Header)
        ]);

        try {
            $markdown = $converter->convert($html);
            return trim($this->decodeEntitiesExceptMarkup($markdown));
        } catch (\Exception $e) {
            return '';
        }
    }

    /**
     * Decode HTML entities so text reads naturally in the Markdown output
     * (e.g. "Tom &amp; Jerry" -> "Tom & Jerry"), but deliberately leave the
     * tag-introducing characters "<" and ">" encoded.
     *
     * A blanket html_entity_decode() would undo the htmlspecialchars() escaping that
     * LlmsTxtController applies to page metadata, and turn markup that was escaped in
     * the source HTML back into live markup. Since the whole point of this output is to
     * be consumed elsewhere - and most Markdown renderers pass raw HTML straight
     * through - that turns editor-controlled page content into stored XSS in the
     * consumer. Leaving "&lt;"/"&gt;" encoded keeps them inert while still rendering as
     * literal "<"/">" wherever the Markdown is displayed.
     */
    private function decodeEntitiesExceptMarkup(string $markdown): string
    {
        return preg_replace_callback(
            '/&(?:#[0-9]{1,7}|#[xX][0-9a-fA-F]{1,6}|[a-zA-Z][a-zA-Z0-9]{1,31});/',
            static function (array $matches): string {
                $decoded = html_entity_decode($matches[0], ENT_QUOTES | ENT_HTML5, 'UTF-8');

                return ($decoded === '<' || $decoded === '>') ? $matches[0] : $decoded;
            },
            $markdown
        ) ?? $markdown;
    }
}
