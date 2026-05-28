<?php

declare(strict_types=1);

namespace App\Service\Forecast\Oceanography;

use DateTimeImmutable;

/**
 * Samples the tide curve across a 24h window starting at the given
 * local instant. Returns the day's min/max plus a series of
 * `{t, h}` pairs (`t` in milliseconds since epoch) suitable for a
 * client-side chart.
 */
final class TideDailyRange
{
    private const SAMPLE_INTERVAL_MIN = 10;

    public function __construct(private readonly TideCalculator $calculator)
    {
    }

    /**
     * @return array{min: float, max: float, series: list<array{t: int, h: float}>}
     */
    public function forDay(float $lat, float $lon, DateTimeImmutable $localMidnight): array
    {
        $min = INF;
        $max = -INF;
        $series = [];
        $startTs = $localMidnight->getTimestamp();
        $stepSeconds = self::SAMPLE_INTERVAL_MIN * 60;
        $count = intdiv(24 * 60, self::SAMPLE_INTERVAL_MIN);

        for ($i = 0; $i < $count; $i++) {
            $ts = $startTs + $i * $stepSeconds;
            $h = $this->calculator->heightForNearLocationAt(
                $lat,
                $lon,
                new DateTimeImmutable('@' . $ts),
            );
            $series[] = ['t' => $ts * 1000, 'h' => round($h, 4)];
            if ($h < $min) {
                $min = $h;
            }
            if ($h > $max) {
                $max = $h;
            }
        }

        return ['min' => $min, 'max' => $max, 'series' => $series];
    }
}
