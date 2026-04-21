<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Service\Forecast\Meteorology\FireRiskLevel;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class FireRiskLevelTest extends TestCase
{
    #[DataProvider('knownLevels')]
    public function testLabelAndBootstrap(int $level, string $label, string $bootstrap): void
    {
        self::assertSame($label, FireRiskLevel::label($level));
        self::assertSame($bootstrap, FireRiskLevel::bootstrap($level));
        self::assertMatchesRegularExpression('/^#[0-9a-fA-F]{6}$/', FireRiskLevel::color($level));
    }

    /**
     * @return iterable<string, array{0:int,1:string,2:string}>
     */
    public static function knownLevels(): iterable
    {
        yield 'low'       => [1, 'fire_risk.level.low',       'success'];
        yield 'moderate'  => [2, 'fire_risk.level.moderate',  'warning'];
        yield 'high'      => [3, 'fire_risk.level.high',      'warning'];
        yield 'very high' => [4, 'fire_risk.level.very_high', 'danger'];
        yield 'maximum'   => [5, 'fire_risk.level.maximum',   'danger'];
    }

    public function testUnknownLevelFallsBackGracefully(): void
    {
        self::assertSame('common.level_unknown', FireRiskLevel::label(42));
        self::assertSame('secondary', FireRiskLevel::bootstrap(42));
        self::assertSame('#adb5bd', FireRiskLevel::color(42));
    }

    public function testAllReturnsLevelsInAscendingSeverity(): void
    {
        self::assertSame([1, 2, 3, 4, 5], FireRiskLevel::all());
    }
}
