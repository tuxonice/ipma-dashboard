<?php

declare(strict_types=1);

namespace App\Service\Observation\Meteorology;

use Tlab\IpmaApi\ApiConnectorInterface;
use Tlab\IpmaApi\Dto\Meteorology\HourlyStationObservation;
use Tlab\IpmaApi\Endpoints;

/**
 * Latest-hour snapshot for every IPMA weather station.
 *
 * Consumes the GeoJSON `obs-surface.geojson` feed. Unlike
 * {@see StationObservationRepository} — which reads a 24h rolling history —
 * this source is a single-hour map feed that carries station metadata
 * (id, name) and geographic coordinates on each feature, making it ideal
 * for a map view.
 *
 * The upstream `tuxonice/ipma-api` release no longer ships a wrapper for
 * this endpoint, so we fetch it through the cached
 * {@see ApiConnectorInterface} directly and hydrate the existing
 * {@see HourlyStationObservation} DTO ourselves.
 */
final class StationHourlyRepository
{
    private const ENDPOINT = Endpoints::BASE_URL . '/observation/meteorology/stations/obs-surface.geojson';

    /**
     * Value IPMA uses to denote missing numeric readings.
     */
    private const MISSING_VALUE = -99.0;

    public function __construct(private readonly ApiConnectorInterface $apiConnector)
    {
    }

    /**
     * @return list<HourlyStationObservation>
     */
    public function all(): array
    {
        /** @var array{features?: list<array<string, mixed>>} $payload */
        $payload = $this->apiConnector->fetchData(self::ENDPOINT);

        $observations = [];
        foreach ($payload['features'] ?? [] as $feature) {
            if (!is_array($feature)) {
                continue;
            }
            $hydrated = $this->hydrate($feature);
            if ($hydrated !== null) {
                $observations[] = $hydrated;
            }
        }

        return $observations;
    }

    /**
     * @param array<string, mixed> $feature
     */
    private function hydrate(array $feature): ?HourlyStationObservation
    {
        $props = $feature['properties'] ?? null;
        $geom = $feature['geometry'] ?? null;

        if (!is_array($props) || !is_array($geom)) {
            return null;
        }

        $coords = $geom['coordinates'] ?? null;
        if (!is_array($coords) || count($coords) < 2) {
            return null;
        }

        return new HourlyStationObservation(
            time: $this->stringOrNull($props['time'] ?? null),
            idEstacao: $this->intOrNull($props['idEstacao'] ?? null),
            localEstacao: $this->stringOrNull($props['localEstacao'] ?? null),
            intensidadeVentoKM: $this->floatOrNull($props['intensidadeVentoKM'] ?? null),
            temperatura: $this->floatOrNull($props['temperatura'] ?? null),
            idDireccVento: $this->intOrNull($props['idDireccVento'] ?? null),
            descDirVento: $this->stringOrNull($props['descDirVento'] ?? null),
            precAcumulada: $this->floatOrNull($props['precAcumulada'] ?? null),
            intensidadeVento: $this->floatOrNull($props['intensidadeVento'] ?? null),
            humidade: $this->floatOrNull($props['humidade'] ?? null),
            pressao: $this->floatOrNull($props['pressao'] ?? null),
            radiacao: $this->floatOrNull($props['radiacao'] ?? null),
            latitude: (float) $coords[1],
            longitude: (float) $coords[0],
        );
    }

    private function floatOrNull(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }
        $float = (float) $value;
        return $float === self::MISSING_VALUE ? null : $float;
    }

    private function intOrNull(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        return (int) $value;
    }

    private function stringOrNull(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $string = (string) $value;
        return $string === '' ? null : $string;
    }
}
