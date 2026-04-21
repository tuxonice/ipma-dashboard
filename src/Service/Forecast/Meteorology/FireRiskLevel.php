<?php

declare(strict_types=1);

namespace App\Service\Forecast\Meteorology;

/**
 * Helper for IPMA's numeric fire-risk level (1-5) as emitted on the
 * `rcm-d{idDay}.json` feed (field `data.rcm`).
 *
 * Matches the official IPMA scale:
 *   1 = Low, 2 = Moderate, 3 = High, 4 = Very high, 5 = Maximum.
 *
 * Colors approximate the palette IPMA uses on ipma.pt for the fire-risk
 * map (yellow → deep red).
 */
final class FireRiskLevel
{
    public const LOW = 1;
    public const MODERATE = 2;
    public const HIGH = 3;
    public const VERY_HIGH = 4;
    public const MAXIMUM = 5;

    /** @var array<int, array{key: string, color: string, bootstrap: string}> */
    private const MAP = [
        self::LOW       => ['key' => 'fire_risk.level.low',       'color' => '#2e7d32', 'bootstrap' => 'success'],
        self::MODERATE  => ['key' => 'fire_risk.level.moderate',  'color' => '#fbc02d', 'bootstrap' => 'warning'],
        self::HIGH      => ['key' => 'fire_risk.level.high',      'color' => '#f57c00', 'bootstrap' => 'warning'],
        self::VERY_HIGH => ['key' => 'fire_risk.level.very_high', 'color' => '#e53935', 'bootstrap' => 'danger'],
        self::MAXIMUM   => ['key' => 'fire_risk.level.maximum',   'color' => '#7b1fa2', 'bootstrap' => 'danger'],
    ];

    /**
     * Translation key for the level. Pass through `|trans` in Twig.
     */
    public static function label(int $level): string
    {
        return self::MAP[$level]['key'] ?? 'common.level_unknown';
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
     * Ordered list of known levels (ascending severity). Useful for building
     * legends and summary tables.
     *
     * @return list<int>
     */
    public static function all(): array
    {
        return [self::LOW, self::MODERATE, self::HIGH, self::VERY_HIGH, self::MAXIMUM];
    }
}
