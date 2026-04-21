<?php

declare(strict_types=1);

namespace App\Service\Observation\Meteorology;

/**
 * Immutable value object for a single IPMA weather-station reading.
 *
 * The upstream JSON uses `-99.0` / `-99` as a sentinel for "no data".
 * We translate sentinels to `null` at construction time so templates can
 * simply check for `null` instead of magic numbers.
 */
final class StationObservation
{
    public const MISSING_SENTINEL = -99.0;

    public function __construct(
        public readonly ?float $temperatureC,
        public readonly ?float $humidityPct,
        public readonly ?float $pressureHpa,
        public readonly ?float $precipitationMm,
        public readonly ?float $radiationWm2,
        public readonly ?float $windSpeedMs,
        public readonly ?float $windSpeedKmh,
        public readonly ?int $windDirectionId,
    ) {
    }

    /**
     * @param array<string, mixed> $raw
     */
    public static function fromArray(array $raw): self
    {
        return new self(
            temperatureC:    self::float($raw['temperatura']        ?? null),
            humidityPct:     self::float($raw['humidade']           ?? null),
            pressureHpa:     self::float($raw['pressao']            ?? null),
            precipitationMm: self::float($raw['precAcumulada']      ?? null),
            radiationWm2:    self::float($raw['radiacao']           ?? null),
            windSpeedMs:     self::float($raw['intensidadeVento']   ?? null),
            windSpeedKmh:    self::float($raw['intensidadeVentoKM'] ?? null),
            windDirectionId: self::int($raw['idDireccVento']        ?? null),
        );
    }

    /**
     * True when every reading is missing (all sentinels). Useful to drop
     * empty station blocks from the UI.
     */
    public function isEmpty(): bool
    {
        return $this->temperatureC === null
            && $this->humidityPct === null
            && $this->pressureHpa === null
            && $this->precipitationMm === null
            && $this->radiationWm2 === null
            && $this->windSpeedMs === null
            && $this->windSpeedKmh === null
            && $this->windDirectionId === null;
    }

    private static function float(mixed $v): ?float
    {
        if ($v === null || $v === '') {
            return null;
        }
        $f = (float) $v;

        return $f <= self::MISSING_SENTINEL + 0.001 ? null : $f;
    }

    private static function int(mixed $v): ?int
    {
        if ($v === null || $v === '') {
            return null;
        }
        // Wind direction 0 means "no wind" — keep it as 0, not null.
        $i = (int) $v;

        return $i < 0 ? null : $i;
    }
}
