<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Service\Forecast\Warnings\AwarenessLevel;
use PHPUnit\Framework\TestCase;

final class AwarenessLevelTest extends TestCase
{
    public function testKnownLevelsExposeMetadata(): void
    {
        $yellow = AwarenessLevel::meta('yellow');
        self::assertSame('awareness.level.yellow', $yellow['label']);
        self::assertSame('yellow', $yellow['color']);
        self::assertSame(1, $yellow['severity']);
    }

    public function testLookupIsCaseInsensitive(): void
    {
        self::assertSame(
            AwarenessLevel::meta('red'),
            AwarenessLevel::meta('RED'),
        );
    }

    public function testUnknownLevelFallsBackToSecondary(): void
    {
        $meta = AwarenessLevel::meta('blue');
        self::assertSame('awareness.level.unknown', $meta['label']);
        self::assertSame('secondary', $meta['color']);
        self::assertSame(-1, $meta['severity']);
    }

    public function testSeverityOrdering(): void
    {
        self::assertLessThan(
            AwarenessLevel::severity('orange'),
            AwarenessLevel::severity('yellow'),
        );
        self::assertLessThan(
            AwarenessLevel::severity('red'),
            AwarenessLevel::severity('orange'),
        );
    }

    public function testGreenIsNotActive(): void
    {
        self::assertFalse(AwarenessLevel::isActive('green'));
        self::assertTrue(AwarenessLevel::isActive('yellow'));
        self::assertTrue(AwarenessLevel::isActive('red'));
    }
}
