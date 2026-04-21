<?php

declare(strict_types=1);

namespace App\Service\Observation\Climate;

/**
 * Helper for classifying Palmer Drought Severity Index (PDSI) values
 * into the standard NOAA categorical bands.
 *
 * Bands (PDSI value):
 *   >=  4.0    Extremely wet
 *    3.0 -  3.99  Very wet
 *    2.0 -  2.99  Moderately wet
 *    1.0 -  1.99  Slightly wet
 *   -0.99 - 0.99  Near normal
 *   -1.0  - -1.99 Mild drought
 *   -2.0  - -2.99 Moderate drought
 *   -3.0  - -3.99 Severe drought
 *   <= -4.0    Extreme drought
 *
 * Reference: NOAA NCEI / IPMA's drought monitor uses the same bands.
 */
final class PdsiLevel
{
    public const EXTREME_DROUGHT = -4;
    public const SEVERE_DROUGHT = -3;
    public const MODERATE_DROUGHT = -2;
    public const MILD_DROUGHT = -1;
    public const NEAR_NORMAL = 0;
    public const SLIGHTLY_WET = 1;
    public const MODERATELY_WET = 2;
    public const VERY_WET = 3;
    public const EXTREMELY_WET = 4;

    /** @var array<int, array{label: string, color: string, text: string}> */
    private const MAP = [
        self::EXTREME_DROUGHT  => ['label' => 'pdsi.level.extreme_drought',  'color' => '#7f1d1d', 'text' => '#ffffff'],
        self::SEVERE_DROUGHT   => ['label' => 'pdsi.level.severe_drought',   'color' => '#c0392b', 'text' => '#ffffff'],
        self::MODERATE_DROUGHT => ['label' => 'pdsi.level.moderate_drought', 'color' => '#e67e22', 'text' => '#212529'],
        self::MILD_DROUGHT     => ['label' => 'pdsi.level.mild_drought',     'color' => '#f1c40f', 'text' => '#212529'],
        self::NEAR_NORMAL      => ['label' => 'pdsi.level.near_normal',      'color' => '#bdc3c7', 'text' => '#212529'],
        self::SLIGHTLY_WET     => ['label' => 'pdsi.level.slightly_wet',     'color' => '#a3d977', 'text' => '#212529'],
        self::MODERATELY_WET   => ['label' => 'pdsi.level.moderately_wet',   'color' => '#3498db', 'text' => '#ffffff'],
        self::VERY_WET         => ['label' => 'pdsi.level.very_wet',         'color' => '#1f6feb', 'text' => '#ffffff'],
        self::EXTREMELY_WET    => ['label' => 'pdsi.level.extremely_wet',    'color' => '#0b3d91', 'text' => '#ffffff'],
    ];

    public static function fromValue(float $value): int
    {
        return match (true) {
            $value <= -4.0 => self::EXTREME_DROUGHT,
            $value <= -3.0 => self::SEVERE_DROUGHT,
            $value <= -2.0 => self::MODERATE_DROUGHT,
            $value <= -1.0 => self::MILD_DROUGHT,
            $value <   1.0 => self::NEAR_NORMAL,
            $value <   2.0 => self::SLIGHTLY_WET,
            $value <   3.0 => self::MODERATELY_WET,
            $value <   4.0 => self::VERY_WET,
            default        => self::EXTREMELY_WET,
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

    public static function textColor(int $level): string
    {
        return self::MAP[$level]['text'] ?? '#212529';
    }

    /**
     * Levels in ascending severity (driest → wettest).
     *
     * @return list<int>
     */
    public static function all(): array
    {
        return [
            self::EXTREME_DROUGHT,
            self::SEVERE_DROUGHT,
            self::MODERATE_DROUGHT,
            self::MILD_DROUGHT,
            self::NEAR_NORMAL,
            self::SLIGHTLY_WET,
            self::MODERATELY_WET,
            self::VERY_WET,
            self::EXTREMELY_WET,
        ];
    }
}
