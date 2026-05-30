<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Service\Forecast\Oceanography\TideCalculator;
use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class TideCalculatorTest extends TestCase
{
    /**
     * Minimal port constants fixture used by tests that need real port data.
     * Values are pinned to the same source as the reference expected heights.
     *
     * @return array<string, mixed>
     */
    private static function fixturePortData(): array
    {
        return [
            'year' => 2026,
            'lisboa' => [
                'name' => 'Lisboa', 'lat' => 38.7073, 'lon' => -9.1363, 'z0' => 2.08,
                'constants' => [
                    'M2' => ['H' => 0.893, 'G' => 84.3],
                    'S2' => ['H' => 0.317, 'G' => 114.1],
                    'K1' => ['H' => 0.071, 'G' => 77.1],
                    'O1' => ['H' => 0.059, 'G' => 336.6],
                ],
            ],
            'sines' => [
                'name' => 'Sines', 'lat' => 37.9564, 'lon' => -8.8703, 'z0' => 1.84,
                'constants' => [
                    'M2' => ['H' => 0.762, 'G' => 84.1],
                    'S2' => ['H' => 0.270, 'G' => 113.5],
                    'K1' => ['H' => 0.060, 'G' => 76.7],
                    'O1' => ['H' => 0.052, 'G' => 334.4],
                ],
            ],
            'funchal' => [
                'name' => 'Funchal', 'lat' => 32.6333, 'lon' => -16.9000, 'z0' => 1.06,
                'constants' => [
                    'M2' => ['H' => 0.601, 'G' => 91.9],
                    'S2' => ['H' => 0.219, 'G' => 122.8],
                    'K1' => ['H' => 0.049, 'G' => 87.3],
                    'O1' => ['H' => 0.042, 'G' => 343.6],
                ],
            ],
            'ponta-delgada' => [
                'name' => 'Ponta Delgada', 'lat' => 37.7333, 'lon' => -25.6667, 'z0' => 0.68,
                'constants' => [
                    'M2' => ['H' => 0.370, 'G' => 113.4],
                    'S2' => ['H' => 0.134, 'G' => 143.8],
                    'K1' => ['H' => 0.031, 'G' => 103.3],
                    'O1' => ['H' => 0.027, 'G' => 357.5],
                ],
            ],
        ];
    }

    private static function fixtureFromFile(): TideCalculator
    {
        $path = sys_get_temp_dir() . '/tide_constants_test.json';
        file_put_contents($path, json_encode(self::fixturePortData(), JSON_THROW_ON_ERROR));

        return TideCalculator::fromFile($path);
    }

    /**
     * Reference values pinned to the current implementation output:
     * 4-constituent harmonic model with per-port z0 and the equilibrium
     * argument V_0 applied at the 2026 epoch. Coordinates are not exact
     * port positions so the test also exercises nearest-port selection.
     *
     * @param array{0: float, 1: float} $coords
     */
    #[DataProvider('referenceHeights')]
    public function testHeightForNearLocationAtMatchesReferenceValues(
        array $coords,
        string $isoTime,
        float $expected,
    ): void {
        $tc = self::fixtureFromFile();

        $actual = $tc->heightForNearLocationAt($coords[0], $coords[1], new DateTimeImmutable($isoTime));

        self::assertEqualsWithDelta($expected, $actual, 1e-3);
    }

    /**
     * @return iterable<string, array{0: array{0: float, 1: float}, 1: string, 2: float}>
     */
    public static function referenceHeights(): iterable
    {
        $t = '2026-07-10T13:45:00Z';

        yield 'Lisbon → lisboa'               => [[38.7223,  -9.1393], $t, 2.5995];
        yield 'Sines → sines'                 => [[37.9482,  -8.8878], $t, 2.2803];
        yield 'Funchal → funchal'             => [[32.6489, -16.9090], $t, 1.4516];
        yield 'Ponta Delgada → ponta-delgada' => [[37.7400, -25.6700], $t, 0.9756];
    }

    public function testEquilibriumArgumentMakesS2ZeroAtMidnightUtcAndShiftsM2Phase(): void
    {
        // With V_0 applied, a port whose only constituent is S2 should
        // peak exactly at the epoch (2026-01-01 00:00 UTC), because S2's
        // equilibrium argument is 0 there. A port whose only constituent
        // is M2 should be near cos(V_0_M2 - g_M2) at the epoch — clearly
        // different from the un-corrected cos(-g_M2).
        $epoch = new DateTimeImmutable('2026-01-01T00:00:00Z');
        $portsS2 = [
            'p' => ['name' => 'P', 'lat' => 0.0, 'lon' => 0.0, 'z0' => 0.0, 'constants' => [
                'M2' => ['H' => 0.0, 'G' => 0.0],
                'S2' => ['H' => 1.0, 'G' => 0.0],
                'K1' => ['H' => 0.0, 'G' => 0.0],
                'O1' => ['H' => 0.0, 'G' => 0.0],
            ]],
        ];
        $tc = new TideCalculator($portsS2, $epoch);

        // S2 has V_0 = 0 at midnight UTC of any day, so cos(0 - 0) = 1.
        self::assertEqualsWithDelta(1.0, $tc->heightForNearLocationAt(0.0, 0.0, $epoch), 1e-9);
    }

    public function testPortZ0IsAddedToTheResultingHeight(): void
    {
        $epoch = new DateTimeImmutable('2026-01-01T00:00:00Z');
        $constants = [
            'M2' => ['H' => 1.0, 'G' => 0.0],
            'S2' => ['H' => 0.0, 'G' => 0.0],
            'K1' => ['H' => 0.0, 'G' => 0.0],
            'O1' => ['H' => 0.0, 'G' => 0.0],
        ];

        $tcZero = new TideCalculator(
            ['p' => ['name' => 'P', 'lat' => 0.0, 'lon' => 0.0, 'z0' => 0.0, 'constants' => $constants]],
            $epoch,
        );
        $tcShifted = new TideCalculator(
            ['p' => ['name' => 'P', 'lat' => 0.0, 'lon' => 0.0, 'z0' => 5.0, 'constants' => $constants]],
            $epoch,
        );

        $t = new DateTimeImmutable('2026-06-15T12:34:00Z');

        self::assertEqualsWithDelta(
            $tcZero->heightForNearLocationAt(0.0, 0.0, $t) + 5.0,
            $tcShifted->heightForNearLocationAt(0.0, 0.0, $t),
            1e-9,
        );
    }

    public function testNeighbouringCoordinatesResolveToTheSamePort(): void
    {
        $tc = self::fixtureFromFile();
        $t = new DateTimeImmutable('2026-07-10T13:45:00Z');

        // Both points sit within the Tagus estuary and should pick Lisboa.
        $a = $tc->heightForNearLocationAt(38.7223, -9.1393, $t);
        $b = $tc->heightForNearLocationAt(38.7100, -9.1500, $t);

        self::assertSame($a, $b);
    }

    public function testFromYearThrowsWhenConstantsFileIsMissing(): void
    {
        $this->expectException(InvalidArgumentException::class);

        TideCalculator::fromYear(1999);
    }
}
