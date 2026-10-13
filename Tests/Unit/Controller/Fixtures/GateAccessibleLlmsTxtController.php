<?php

declare(strict_types=1);

namespace WebVision\AiLlmsTxt\Tests\Unit\Controller\Fixtures;

use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Http\PropagateResponseException;
use TYPO3\CMS\Core\Http\Response;
use WebVision\AiLlmsTxt\Controller\LlmsTxtController;

/**
 * Exposes the Markdown access gate and stubs out the ErrorController lookup, so the
 * gate's decision can be asserted without a full frontend/site error handling setup.
 */
final class GateAccessibleLlmsTxtController extends LlmsTxtController
{
    /**
     * @param array<string, mixed> $page
     */
    public function runGate(ServerRequestInterface $request, array $page): void
    {
        $this->assertMarkdownOutputIsAllowed($request, $page);
    }

    protected function pageNotFound(ServerRequestInterface $request, string $message): PropagateResponseException
    {
        return new PropagateResponseException(new Response('php://temp', 404), 1769076609);
    }
}
