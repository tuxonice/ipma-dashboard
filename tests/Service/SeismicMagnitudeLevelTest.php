<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Service\Observation\Seismic\SeismicMagnitudeLevel;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SeismicMagnitudeLevelTest extends TestCase
{
    #[DataProvider('magnitudeBands')]
    public function testFromMagnitude(float $magnitude, int $expected): void
    {
        self::assertSame($expected, SeismicMagnitudeLevel::fromMagnitude($magnitude));
    }

    /**
     * @return iterable<string, array{0: float, 1: int}>
     */
    public static function magnitudeBands(): iterable
    {
        yield 'sentinel'       => [-99.0, SeismicMagnitudeLevel::UNKNOWN];
        yield 'micro low'      => [0.5,   SeismicMagnitudeLevel::MICRO];
        yield 'micro upper'    => [1.99,  SeismicMagnitudeLevel::MICRO];
        yield 'minor lower'    => [2.0,   SeismicMagnitudeLevel::MINOR];
        yield 'minor upper'    => [3.99,  SeismicMagnitudeLevel::MINOR];
        yield 'light lower'    => [4.0,   SeismicMagnitudeLevel::LIGHT];
        yield 'light upper'    => [4.99,  SeismicMagnitudeLevel::LIGHT];
        yield 'moderate lower' => [5.0,   SeismicMagnitudeLevel::MODERATE];
        yield 'moderate upper' => [5.99,  SeismicMagnitudeLevel::MODERATE];
        yield 'strong lower'   => [6.0,   SeismicMagnitudeLevel::STRONG];
        yield 'strong upper'   => [6.99,  SeismicMagnitudeLevel::STRONG];
        yield 'major lower'    => [7.0,   SeismicMagnitudeLevel::MAJOR];
        yield 'major high'     => [9.5,   SeismicMagnitudeLevel::MAJOR];
    }

    public function testIsReportedRejectsSentinel(): void
    {
        self::assertFalse(SeismicMagnitudeLevel::isReported(-99.0));
        self::assertTrue(SeismicMagnitudeLevel::isReported(0.0));
        self::assertTrue(SeismicMagnitudeLevel::isReported(3.5));
    }

    public function testLabelAndBootstrap(): void
    {
        foreach (SeismicMagnitudeLevel::all() as $level) {
            self::assertNotSame('', SeismicMagnitudeLevel::label($level));
            self::assertMatchesRegularExpression('/^#[0-9a-fA-F]{6}$/', SeismicMagnitudeLevel::color($level));
            self::assertGreaterThan(0, SeismicMagnitudeLevel::radius($level));
        }
    }

    public function testAllExcludesUnknownAndIsAscending(): void
    {
        $levels = SeismicMagnitudeLevel::all();
        self::assertNotContains(SeismicMagnitudeLevel::UNKNOWN, $levels);
        self::assertSame($levels, [...$levels]);
        $sorted = $levels;
        sort($sorted);
        self::assertSame($sorted, $levels);
    }
}
