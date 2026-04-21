<?php

declare(strict_types=1);

namespace App\Service\Services;

use Psr\SimpleCache\CacheInterface;
use Tlab\IpmaApi\Dto\Service\DistrictLocation;
use Tlab\IpmaApi\IpmaService;

/**
 * Thin repository over the IPMA "districts & islands locations" endpoint.
 *
 * Keeps controllers free of IPMA-specific call chains and centralises the
 * construction of the underlying API service.
 */
final class LocationRepository
{
    public function __construct(private readonly CacheInterface $cache)
    {
    }

    /**
     * @return list<DistrictLocation>
     */
    public function all(): array
    {
        return IpmaService::createDistrictsIslandsLocationsApi($this->cache)
            ->query()
            ->get();
    }

    public function findByGlobalId(int $globalIdLocal): ?DistrictLocation
    {
        $matches = IpmaService::createDistrictsIslandsLocationsApi($this->cache)
            ->query()
            ->filterByGlobalIdLocal($globalIdLocal)
            ->get();

        return $matches[0] ?? null;
    }

    public function findByIdWarningArea(string $idWarningArea): ?DistrictLocation
    {
        $matches = IpmaService::createDistrictsIslandsLocationsApi($this->cache)
            ->query()
            ->filterByIdWarningArea($idWarningArea)
            ->get();

        return $matches[0] ?? null;
    }

    /**
     * Group locations by region id (1 = mainland, 2 = Madeira, 3 = Azores).
     *
     * @return array<int, list<DistrictLocation>>
     */
    public function groupedByRegion(): array
    {
        $grouped = [];
        foreach ($this->all() as $location) {
            $grouped[$location->idRegion][] = $location;
        }

        foreach ($grouped as &$bucket) {
            usort($bucket, static fn(DistrictLocation $a, DistrictLocation $b) => strcmp($a->name, $b->name));
        }
        unset($bucket);

        ksort($grouped);

        return $grouped;
    }

    /**
     * Translation key for an IPMA region id; resolve with `|trans` in Twig.
     */
    public static function regionLabel(int $idRegion): string
    {
        return match ($idRegion) {
            1 => 'region.mainland',
            2 => 'region.madeira',
            3 => 'region.azores',
            default => 'region.unknown',
        };
    }

    /**
     * IANA timezone identifier for an IPMA region id.
     */
    public static function regionTimezone(int $idRegion): string
    {
        return match ($idRegion) {
            2 => 'Atlantic/Madeira',
            3 => 'Atlantic/Azores',
            default => 'Europe/Lisbon',
        };
    }
}
