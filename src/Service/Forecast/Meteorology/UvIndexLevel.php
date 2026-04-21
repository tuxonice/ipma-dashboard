<?php

declare(strict_types=1);

namespace App\Service\Forecast\Meteorology;

/**
 * Helper for IPMA's ultraviolet (UV) index forecast (`iUv` field on the
 * `forecast/meteorology/uv/uv.json` feed).
 *
 * Bands follow the WHO global solar UV index categories:
 *   0-2   Low
 *   3-5   Moderate
 *   6-7   High
 *   8-10  Very high
 *   11+   Extreme
 *
 * Colours approximate the WHO/IPMA palette (green to violet).
 */
final class UvIndexLevel
{
    public const LOW = 1;
    public const MODERATE = 2;
    public const HIGH = 3;
    public const VERY_HIGH = 4;
    public const EXTREME = 5;

    /** @var array<int, array{label: string, color: string, bootstrap: string, text: string}> */
    private const MAP = [
        self::LOW       => ['label' => 'uv.level.low',       'color' => '#4caf50', 'bootstrap' => 'success', 'text' => '#ffffff'],
        self::MODERATE  => ['label' => 'uv.level.moderate',  'color' => '#ffeb3b', 'bootstrap' => 'warning', 'text' => '#212529'],
        self::HIGH      => ['label' => 'uv.level.high',      'color' => '#ff9800', 'bootstrap' => 'warning', 'text' => '#212529'],
        self::VERY_HIGH => ['label' => 'uv.level.very_high', 'color' => '#f44336', 'bootstrap' => 'danger',  'text' => '#ffffff'],
        self::EXTREME   => ['label' => 'uv.level.extreme',   'color' => '#9c27b0', 'bootstrap' => 'danger',  'text' => '#ffffff'],
    ];

    public static function fromUvIndex(float $uvIndex): int
    {
        return match (true) {
            $uvIndex < 3.0  => self::LOW,
            $uvIndex < 6.0  => self::MODERATE,
            $uvIndex < 8.0  => self::HIGH,
            $uvIndex < 11.0 => self::VERY_HIGH,
            default         => self::EXTREME,
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

    public static function textColor(int $level): string
    {
        return self::MAP[$level]['text'] ?? '#212529';
    }

    /**
     * Ordered list of known levels (ascending severity). Useful for legends
     * and summary tables.
     *
     * @return list<int>
     */
    public static function all(): array
    {
        return [self::LOW, self::MODERATE, self::HIGH, self::VERY_HIGH, self::EXTREME];
    }

    /**
     * Recommended exposure advice per band (kept short for tile UI).
     */
    public static function advice(int $level): string
    {
        return match ($level) {
            self::LOW       => 'uv.advice.low',
            self::MODERATE  => 'uv.advice.moderate',
            self::HIGH      => 'uv.advice.high',
            self::VERY_HIGH => 'uv.advice.very_high',
            self::EXTREME   => 'uv.advice.extreme',
            default         => '',
        };
    }
}
