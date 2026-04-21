<?php

declare(strict_types=1);

namespace App\Service\Observation\Meteorology;

use Psr\SimpleCache\CacheInterface;
use Tlab\IpmaApi\Dto\Service\WeatherStation;
use Tlab\IpmaApi\IpmaService;

/**
 * Repository over the IPMA "weather stations" endpoint (the static station
 * catalog — id, name, coordinates). Observation readings are handled by
 * {@see StationObservationRepository}.
 */
final class StationRepository
{
    public function __construct(private readonly CacheInterface $cache)
    {
    }

    /**
     * @return list<WeatherStation>
     */
    public function all(): array
    {
        return IpmaService::createWeatherStationsApi($this->cache)
            ->query()
            ->get();
    }

    /**
     * @return list<WeatherStation> Sorted alphabetically by name.
     */
    public function sortedByName(): array
    {
        $stations = $this->all();
        usort($stations, static fn(WeatherStation $a, WeatherStation $b) => strcmp($a->name, $b->name));

        return $stations;
    }

    public function findById(int $id): ?WeatherStation
    {
        $matches = IpmaService::createWeatherStationsApi($this->cache)
            ->query()
            ->filterById($id)
            ->get();

        return $matches[0] ?? null;
    }
}
