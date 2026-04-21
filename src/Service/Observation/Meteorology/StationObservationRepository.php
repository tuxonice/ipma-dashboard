<?php

declare(strict_types=1);

namespace App\Service\Observation\Meteorology;

use DateTimeImmutable;
use Tlab\IpmaApi\ApiConnectorInterface;
use Tlab\IpmaApi\Endpoints;
use Tlab\IpmaApi\Exception\IpmaApiException;

/**
 * Reads the IPMA station observations JSON.
 *
 * The upstream `tuxonice/ipma-api` release does not wrap this endpoint yet,
 * so we use the cached {@see ApiConnectorInterface} directly. The response
 * shape is:
 *
 * ```json
 * {
 *   "2026-04-23T09:00": {
 *     "1210881": { "temperatura": 16.3, "humidade": 70, ... },
 *     ...
 *   },
 *   "2026-04-23T08:00": { ... }
 * }
 * ```
 *
 * Timestamps are naive (no timezone) — IPMA publishes them in UTC. We parse
 * them as UTC to keep sorting and history queries deterministic.
 */
final class StationObservationRepository
{
    public function __construct(private readonly ApiConnectorInterface $apiConnector)
    {
    }

    /**
     * Latest readings across all stations.
     *
     * Walks timestamps newest-first and returns the first one that contains
     * at least one non-empty station block. Individual empty stations are
     * dropped from the returned map.
     *
     * @return array{at: ?DateTimeImmutable, observations: array<int, StationObservation>}
     */
    public function latestAll(): array
    {
        $timestamps = $this->timestampsDesc();

        foreach ($timestamps as $timestampLabel => $at) {
            $observations = [];
            foreach ($this->rawForTimestamp($timestampLabel) as $stationId => $raw) {
                if (!is_array($raw)) {
                    continue;
                }
                $obs = StationObservation::fromArray($raw);
                if ($obs->isEmpty()) {
                    continue;
                }
                $observations[(int) $stationId] = $obs;
            }

            if ($observations !== []) {
                return ['at' => $at, 'observations' => $observations];
            }
        }

        return ['at' => null, 'observations' => []];
    }

    /**
     * Ordered history (oldest → newest) of observations for a single station.
     *
     * @return list<array{at: DateTimeImmutable, observation: StationObservation}>
     */
    public function historyForStation(int $stationId): array
    {
        $timestamps = $this->timestampsAsc();
        $key = (string) $stationId;
        $history = [];

        foreach ($timestamps as $timestampLabel => $at) {
            $raw = $this->rawForTimestamp($timestampLabel);
            if (!isset($raw[$key]) || !is_array($raw[$key])) {
                continue;
            }

            $obs = StationObservation::fromArray($raw[$key]);
            if ($obs->isEmpty()) {
                continue;
            }

            $history[] = ['at' => $at, 'observation' => $obs];
        }

        return $history;
    }

    /**
     * @return array<string, DateTimeImmutable> Map of raw label → parsed timestamp, newest first.
     */
    private function timestampsDesc(): array
    {
        $timestamps = $this->timestampsAsc();

        return array_reverse($timestamps, preserve_keys: true);
    }

    /**
     * @return array<string, DateTimeImmutable> Map of raw label → parsed timestamp, oldest first.
     */
    private function timestampsAsc(): array
    {
        $data = $this->payload();
        $parsed = [];

        foreach (array_keys($data) as $label) {
            $label = (string) $label;
            try {
                // IPMA labels look like "2026-04-23T09:00" with no timezone.
                $parsed[$label] = new DateTimeImmutable($label . 'Z');
            } catch (\Exception) {
                continue;
            }
        }

        uasort(
            $parsed,
            static fn(DateTimeImmutable $a, DateTimeImmutable $b) => $a <=> $b,
        );

        return $parsed;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function rawForTimestamp(string $timestampLabel): array
    {
        $data = $this->payload();
        $bucket = $data[$timestampLabel] ?? [];

        return is_array($bucket) ? $bucket : [];
    }

    /**
     * @return array<string, mixed>
     * @throws IpmaApiException
     */
    private function payload(): array
    {
        /** @var array<string, mixed> $data */
        $data = $this->apiConnector->fetchData(Endpoints::WEATHER_STATION_OBSERVATION);

        return $data;
    }
}
