<?php

declare(strict_types=1);

namespace App\Service\Observation\Meteorology;

/**
 * Helper for IPMA's `idDireccVento` field used in station observations.
 *
 * IPMA encodes wind direction as an integer 0-9 (0 = no wind; 1-8 are the
 * eight cardinal / inter-cardinal directions; 9 wraps back to North).
 */
final class StationWindDirection
{
    /** @var array<int, array{code: string, label: string}> */
    private const MAP = [
        0 => ['code' => '',   'label' => 'wind.direction.none'],
        1 => ['code' => 'N',  'label' => 'wind.direction.n'],
        2 => ['code' => 'NE', 'label' => 'wind.direction.ne'],
        3 => ['code' => 'E',  'label' => 'wind.direction.e'],
        4 => ['code' => 'SE', 'label' => 'wind.direction.se'],
        5 => ['code' => 'S',  'label' => 'wind.direction.s'],
        6 => ['code' => 'SW', 'label' => 'wind.direction.sw'],
        7 => ['code' => 'W',  'label' => 'wind.direction.w'],
        8 => ['code' => 'NW', 'label' => 'wind.direction.nw'],
        9 => ['code' => 'N',  'label' => 'wind.direction.n'],
    ];

    public static function code(?int $id): string
    {
        if ($id === null) {
            return '';
        }

        return self::MAP[$id]['code'] ?? '';
    }

    /**
     * Translation key for the wind direction; resolve with `|trans` in Twig.
     */
    public static function label(?int $id): string
    {
        if ($id === null) {
            return 'wind.direction.unknown';
        }

        return self::MAP[$id]['label'] ?? 'wind.direction.unknown';
    }
}
