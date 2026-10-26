<?php

declare(strict_types=1);

namespace WebVision\AiLlmsTxt\Tests\Unit\Controller\Fixtures;

use WebVision\AiLlmsTxt\Controller\LlmsTxtController;

/**
 * Exposes how page title, description and rendered content are combined, so this can be
 * asserted without rendering content elements through TypoScript.
 */
final class PageHtmlAccessibleLlmsTxtController extends LlmsTxtController
{
    public function runComposePageHtml(string $title, string $description, string $renderedContent): string
    {
        return $this->composePageHtml($title, $description, $renderedContent);
    }
}
