<?php

declare(strict_types=1);

namespace App\Service\Observation\Biology;

use Psr\SimpleCache\CacheInterface;
use Tlab\IpmaApi\Dto\Biology\MolluscFeature;
use Tlab\IpmaApi\IpmaObservation;

/**
 * Repository over IPMA's bivalve mollusc harvesting interdictions feed
 * (`/observation/biology/bivalves/CI_SNMB.geojson`).
 *
 * Returns the published production zones with their current status and
 * the per-species "open" / "close" breakdown that the upstream feed
 * keeps inside the `properties.interdictions` GeoJSON object.
 */
final class BivalveRepository
{
    public function __construct(private readonly CacheInterface $cache)
    {
    }

    /**
     * @return array{
     *     features: list<MolluscFeature>,
     *     metadata: array<string, mixed>,
     * }
     */
    public function all(): array
    {
        $api = IpmaObservation::createMolluscHarvestingProhibitionApi($this->cache)->from();

        return [
            'features' => $api->get(),
            'metadata' => $api->getMetaData(),
        ];
    }

    /**
     * Pulls the per-species interdiction lists from a feature's raw
     * GeoJSON properties.
     *
     * @return array{open: list<array<string,string>>, close: list<array<string,string>>}
     */
    public static function interdictions(MolluscFeature $feature): array
    {
        $raw = $feature->raw();
        $interdictions = $raw['properties']['interdictions'] ?? [];

        return [
            'open' => array_values($interdictions['open'] ?? []),
            'close' => array_values($interdictions['close'] ?? []),
        ];
    }
}
