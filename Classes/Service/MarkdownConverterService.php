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
            $markdown = html_entity_decode($markdown, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            return trim($markdown);
        } catch (\Exception $e) {
            return '';
        }
    }
}
