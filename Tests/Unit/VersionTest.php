<?php

declare(strict_types=1);

namespace WebVision\AiLlmsTxt\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Information\Typo3Version;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

final class VersionTest extends UnitTestCase
{
    #[Test]
    public function isSupportedCoreVersion(): void
    {
        static::assertContains((new Typo3Version())->getMajorVersion(), [12, 13, 14]);
    }
}
