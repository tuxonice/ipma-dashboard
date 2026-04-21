<?php

declare(strict_types=1);

namespace App\Service\Observation\Seismic;

/**
 * Helper for classifying earthquake magnitudes (Richter / Mw) into the
 * conventional severity bands used on the IPMA seismic dashboard.
 *
 * Bands (loosely following USGS/Richter qualitative descriptors):
 *   < 2.0      Micro
 *   2.0 - 3.9  Minor
 *   4.0 - 4.9  Light
 *   5.0 - 5.9  Moderate
 *   6.0 - 6.9  Strong
 *   >= 7.0     Major
 *
 * IPMA's seismic feed uses `-99.0` as a sentinel meaning "no magnitude
 * reported"; {@see self::isReported()} returns false for those values.
 */
final class SeismicMagnitudeLevel
{
    public const UNKNOWN = 0;
    public const MICRO = 1;
    public const MINOR = 2;
    public const LIGHT = 3;
    public const MODERATE = 4;
    public const STRONG = 5;
    public const MAJOR = 6;

    private const SENTINEL = -99.0;

    /** @var array<int, array{label: string, color: string, bootstrap: string}> */
    private const MAP = [
        self::UNKNOWN  => ['label' => 'seismic.magnitude.unknown',  'color' => '#adb5bd', 'bootstrap' => 'secondary'],
        self::MICRO    => ['label' => 'seismic.magnitude.micro',    'color' => '#6c757d', 'bootstrap' => 'secondary'],
        self::MINOR    => ['label' => 'seismic.magnitude.minor',    'color' => '#2e7d32', 'bootstrap' => 'success'],
        self::LIGHT    => ['label' => 'seismic.magnitude.light',    'color' => '#fbc02d', 'bootstrap' => 'warning'],
        self::MODERATE => ['label' => 'seismic.magnitude.moderate', 'color' => '#f57c00', 'bootstrap' => 'warning'],
        self::STRONG   => ['label' => 'seismic.magnitude.strong',   'color' => '#e53935', 'bootstrap' => 'danger'],
        self::MAJOR    => ['label' => 'seismic.magnitude.major',    'color' => '#7b1fa2', 'bootstrap' => 'danger'],
    ];

    public static function isReported(float $magnitude): bool
    {
        return $magnitude > self::SENTINEL + 0.001;
    }

    public static function fromMagnitude(float $magnitude): int
    {
        if (!self::isReported($magnitude)) {
            return self::UNKNOWN;
        }

        return match (true) {
            $magnitude < 2.0 => self::MICRO,
            $magnitude < 4.0 => self::MINOR,
            $magnitude < 5.0 => self::LIGHT,
            $magnitude < 6.0 => self::MODERATE,
            $magnitude < 7.0 => self::STRONG,
            default          => self::MAJOR,
        };
    }

    public static function label(int $level): string
    {
        return self::MAP[$level]['label'] ?? 'common.level_unknown';
    }

    public static function color(int $level): string
    {
        return self::MAP[$level]['color'] ?? '#adb5bd';
    }

    public static function bootstrap(int $level): string
    {
        return self::MAP[$level]['bootstrap'] ?? 'secondary';
    }

    /**
     * Marker radius (px) for plotting events on a Leaflet map; scales
     * roughly with felt impact rather than literal magnitude so micro
     * events don't disappear.
     */
    public static function radius(int $level): int
    {
        return match ($level) {
            self::UNKNOWN  => 4,
            self::MICRO    => 4,
            self::MINOR    => 5,
            self::LIGHT    => 7,
            self::MODERATE => 9,
            self::STRONG   => 12,
            self::MAJOR    => 16,
            default        => 5,
        };
    }

    /**
     * Ordered list of bands (ascending severity), excluding the
     * "unknown" placeholder so it can drive a legend.
     *
     * @return list<int>
     */
    public static function all(): array
    {
        return [self::MICRO, self::MINOR, self::LIGHT, self::MODERATE, self::STRONG, self::MAJOR];
    }
}
