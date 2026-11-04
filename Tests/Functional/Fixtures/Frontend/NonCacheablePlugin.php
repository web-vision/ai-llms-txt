<?php

declare(strict_types=1);

namespace WebVision\AiLlmsTxt\Tests\Functional\Fixtures\Frontend;

use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Attribute\AsAllowedCallable;

/**
 * A plugin rendered as USER_INT, see page.typoscript
 */
final class NonCacheablePlugin
{
    /**
     * @param array<string, mixed> $conf
     */
    #[AsAllowedCallable]
    public function render(string $content, array $conf, ServerRequestInterface $request): string
    {
        return '<ul><li>Marketing (B.A.)</li><li>Logistics (M.Sc.)</li></ul>';
    }
}
