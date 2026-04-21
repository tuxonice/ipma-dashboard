<?php

declare(strict_types=1);

namespace App\Service\Forecast\Meteorology;

use Psr\SimpleCache\CacheInterface;
use Tlab\IpmaApi\Dto\Forecast\UltravioletRiskRecord;
use Tlab\IpmaApi\IpmaForecast;

/**
 * Repository over IPMA's ultraviolet-risk forecast endpoint
 * (`/forecast/meteorology/uv/uv.json`).
 *
 * Returns the next ~3 days of UV index values per location, indexed by
 * `globalIdLocal` and sorted chronologically.
 */
final class UvIndexRepository
{
    public function __construct(private readonly CacheInterface $cache)
    {
    }

    /**
     * @return list<UltravioletRiskRecord>
     */
    public function all(): array
    {
        return IpmaForecast::createUltravioletRiskForecastApi($this->cache)->get();
    }

    /**
     * Returns the published forecast records grouped by `globalIdLocal`.
     * Each per-location bucket is sorted by `forecastDate` ascending.
     *
     * @return array<int, list<UltravioletRiskRecord>>
     */
    public function groupedByLocation(): array
    {
        $grouped = [];
        foreach ($this->all() as $record) {
            $grouped[$record->globalIdLocal][] = $record;
        }

        foreach ($grouped as &$bucket) {
            usort(
                $bucket,
                static fn(UltravioletRiskRecord $a, UltravioletRiskRecord $b)
                    => strcmp($a->forecastDate, $b->forecastDate),
            );
        }
        unset($bucket);

        return $grouped;
    }

    /**
     * Sorted list of distinct forecast dates present in the feed (ascending).
     *
     * @return list<string>
     */
    public function forecastDates(): array
    {
        $dates = [];
        foreach ($this->all() as $record) {
            $dates[$record->forecastDate] = true;
        }

        $list = array_keys($dates);
        sort($list);

        return $list;
    }
}
