<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Service\Observation\Meteorology\StationObservation;
use PHPUnit\Framework\TestCase;

final class StationObservationTest extends TestCase
{
    public function testFromArrayMapsFields(): void
    {
        $obs = StationObservation::fromArray([
            'temperatura'        => 16.3,
            'humidade'           => 70.0,
            'pressao'            => 1014.4,
            'precAcumulada'      => 0.2,
            'radiacao'           => 914.1,
            'intensidadeVento'   => 0.9,
            'intensidadeVentoKM' => 3.2,
            'idDireccVento'      => 4,
        ]);

        self::assertSame(16.3, $obs->temperatureC);
        self::assertSame(70.0, $obs->humidityPct);
        self::assertSame(1014.4, $obs->pressureHpa);
        self::assertSame(0.2, $obs->precipitationMm);
        self::assertSame(914.1, $obs->radiationWm2);
        self::assertSame(0.9, $obs->windSpeedMs);
        self::assertSame(3.2, $obs->windSpeedKmh);
        self::assertSame(4, $obs->windDirectionId);
        self::assertFalse($obs->isEmpty());
    }

    public function testSentinelValuesBecomeNull(): void
    {
        $obs = StationObservation::fromArray([
            'temperatura'        => 16.3,
            'humidade'           => 70.0,
            'pressao'            => -99.0,
            'precAcumulada'      => -99.0,
            'radiacao'           => -99.0,
            'intensidadeVento'   => -99.0,
            'intensidadeVentoKM' => -99.0,
            'idDireccVento'      => 0,
        ]);

        self::assertSame(16.3, $obs->temperatureC);
        self::assertSame(70.0, $obs->humidityPct);
        self::assertNull($obs->pressureHpa);
        self::assertNull($obs->precipitationMm);
        self::assertNull($obs->radiationWm2);
        self::assertNull($obs->windSpeedMs);
        self::assertNull($obs->windSpeedKmh);
        self::assertSame(0, $obs->windDirectionId, '0 means "no wind" and must be preserved.');
    }

    public function testIsEmptyWhenEveryFieldIsSentinel(): void
    {
        $obs = StationObservation::fromArray([
            'temperatura'        => -99.0,
            'humidade'           => -99.0,
            'pressao'            => -99.0,
            'precAcumulada'      => -99.0,
            'radiacao'           => -99.0,
            'intensidadeVento'   => -99.0,
            'intensidadeVentoKM' => -99.0,
            'idDireccVento'      => -1,
        ]);

        self::assertTrue($obs->isEmpty());
    }

    public function testMissingKeysAreNull(): void
    {
        $obs = StationObservation::fromArray([]);

        self::assertTrue($obs->isEmpty());
    }
}
