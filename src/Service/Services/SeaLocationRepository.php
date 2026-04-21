<?php

declare(strict_types=1);

namespace App\Service\Services;

use Psr\SimpleCache\CacheInterface;
use Tlab\IpmaApi\Dto\Service\SeaLocation;
use Tlab\IpmaApi\IpmaService;

/**
 * Repository over the IPMA "sea locations" endpoint (coastal forecast points).
 *
 * Mirrors the shape of {@see LocationRepository} so templates can reuse the
 * same region-grouping pattern.
 */
final class SeaLocationRepository
{
    public function __construct(private readonly CacheInterface $cache)
    {
    }

    /**
     * @return list<SeaLocation>
     */
    public function all(): array
    {
        return IpmaService::createSeaLocationsApi($this->cache)
            ->query()
            ->get();
    }

    public function findByGlobalId(int $globalIdLocal): ?SeaLocation
    {
        $matches = IpmaService::createSeaLocationsApi($this->cache)
            ->query()
            ->filterByGlobalIdLocal($globalIdLocal)
            ->get();

        return $matches[0] ?? null;
    }

    /**
     * @return array<int, list<SeaLocation>>
     */
    public function groupedByRegion(): array
    {
        $grouped = [];
        foreach ($this->all() as $location) {
            $grouped[$location->idRegion][] = $location;
        }

        foreach ($grouped as &$bucket) {
            usort($bucket, static fn(SeaLocation $a, SeaLocation $b) => strcmp($a->name, $b->name));
        }
        unset($bucket);

        ksort($grouped);

        return $grouped;
    }
}
