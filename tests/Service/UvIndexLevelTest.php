<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Service\Forecast\Meteorology\UvIndexLevel;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class UvIndexLevelTest extends TestCase
{
    #[DataProvider('uvIndexBands')]
    public function testFromUvIndexMapsToWhoBand(float $uv, int $expectedLevel): void
    {
        self::assertSame($expectedLevel, UvIndexLevel::fromUvIndex($uv));
    }

    /**
     * @return iterable<string, array{0: float, 1: int}>
     */
    public static function uvIndexBands(): iterable
    {
        yield 'zero'                => [0.0,  UvIndexLevel::LOW];
        yield 'low upper bound'     => [2.9,  UvIndexLevel::LOW];
        yield 'moderate lower'      => [3.0,  UvIndexLevel::MODERATE];
        yield 'moderate upper'      => [5.9,  UvIndexLevel::MODERATE];
        yield 'high lower'          => [6.0,  UvIndexLevel::HIGH];
        yield 'high upper'          => [7.9,  UvIndexLevel::HIGH];
        yield 'very high lower'     => [8.0,  UvIndexLevel::VERY_HIGH];
        yield 'very high upper'     => [10.9, UvIndexLevel::VERY_HIGH];
        yield 'extreme lower'       => [11.0, UvIndexLevel::EXTREME];
        yield 'extreme high'        => [15.0, UvIndexLevel::EXTREME];
    }

    #[DataProvider('knownLevels')]
    public function testLabelAndBootstrap(int $level, string $label, string $bootstrap): void
    {
        self::assertSame($label, UvIndexLevel::label($level));
        self::assertSame($bootstrap, UvIndexLevel::bootstrap($level));
        self::assertMatchesRegularExpression('/^#[0-9a-fA-F]{6}$/', UvIndexLevel::color($level));
        self::assertMatchesRegularExpression('/^#[0-9a-fA-F]{6}$/', UvIndexLevel::textColor($level));
        self::assertNotSame('', UvIndexLevel::advice($level));
    }

    /**
     * @return iterable<string, array{0: int, 1: string, 2: string}>
     */
    public static function knownLevels(): iterable
    {
        yield 'low'       => [UvIndexLevel::LOW,       'uv.level.low',       'success'];
        yield 'moderate'  => [UvIndexLevel::MODERATE,  'uv.level.moderate',  'warning'];
        yield 'high'      => [UvIndexLevel::HIGH,      'uv.level.high',      'warning'];
        yield 'very high' => [UvIndexLevel::VERY_HIGH, 'uv.level.very_high', 'danger'];
        yield 'extreme'   => [UvIndexLevel::EXTREME,   'uv.level.extreme',   'danger'];
    }

    public function testUnknownLevelFallsBackGracefully(): void
    {
        self::assertSame('common.level_unknown', UvIndexLevel::label(42));
        self::assertSame('secondary', UvIndexLevel::bootstrap(42));
        self::assertSame('#adb5bd', UvIndexLevel::color(42));
        self::assertSame('', UvIndexLevel::advice(42));
    }

    public function testAllReturnsLevelsInAscendingSeverity(): void
    {
        self::assertSame([1, 2, 3, 4, 5], UvIndexLevel::all());
    }
}
