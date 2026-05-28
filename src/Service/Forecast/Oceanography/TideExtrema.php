<?php

declare(strict_types=1);

namespace App\Service\Forecast\Oceanography;

/**
 * Detects local maxima (high tide) and local minima (low tide) in a
 * sampled tide series. Resolution is bounded by the sample interval —
 * with 10-minute samples, extrema timing is accurate to ±5 minutes.
 */
final class TideExtrema
{
    /**
     * @param list<array{t: int, h: float}> $series
     *
     * @return list<array{t: int, h: float, type: string}>
     */
    public static function findInSeries(array $series): array
    {
        $extrema = [];
        $n = count($series);

        for ($i = 1; $i < $n - 1; $i++) {
            $prev = $series[$i - 1]['h'];
            $curr = $series[$i]['h'];
            $next = $series[$i + 1]['h'];

            if ($curr > $prev && $curr > $next) {
                $extrema[] = ['t' => $series[$i]['t'], 'h' => $curr, 'type' => 'high'];
            } elseif ($curr < $prev && $curr < $next) {
                $extrema[] = ['t' => $series[$i]['t'], 'h' => $curr, 'type' => 'low'];
            }
        }

        return $extrema;
    }
}
