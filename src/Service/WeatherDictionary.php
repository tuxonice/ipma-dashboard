<?php

declare(strict_types=1);

namespace App\Service;

/**
 * Static lookup tables for IPMA categorical codes.
 *
 * The `tuxonice/ipma-api` package does not currently expose the weather-type
 * / wind-class / precipitation-class reference endpoints, so we embed the
 * documented values here. Labels follow IPMA's English descriptions; icons
 * are Bootstrap Icons class names.
 *
 * Source: https://api.ipma.pt/open-data/weather-type-classe.json (frozen copy)
 */
final class WeatherDictionary
{
    /**
     * @var array<int, array{label: string, icon: string}>
     */
    private const WEATHER_TYPES = [
        0  => ['label' => 'weather.type.0',  'icon' => 'bi-question-circle'],
        1  => ['label' => 'weather.type.1',  'icon' => 'bi-sun'],
        2  => ['label' => 'weather.type.2',  'icon' => 'bi-cloud-sun'],
        3  => ['label' => 'weather.type.3',  'icon' => 'bi-cloud-sun'],
        4  => ['label' => 'weather.type.4',  'icon' => 'bi-clouds'],
        5  => ['label' => 'weather.type.5',  'icon' => 'bi-clouds'],
        6  => ['label' => 'weather.type.6',  'icon' => 'bi-cloud-rain'],
        7  => ['label' => 'weather.type.7',  'icon' => 'bi-cloud-drizzle'],
        8  => ['label' => 'weather.type.8',  'icon' => 'bi-cloud-rain-heavy'],
        9  => ['label' => 'weather.type.9',  'icon' => 'bi-cloud-rain'],
        10 => ['label' => 'weather.type.10', 'icon' => 'bi-cloud-drizzle'],
        11 => ['label' => 'weather.type.11', 'icon' => 'bi-cloud-rain-heavy'],
        12 => ['label' => 'weather.type.12', 'icon' => 'bi-cloud-rain'],
        13 => ['label' => 'weather.type.13', 'icon' => 'bi-cloud-drizzle'],
        14 => ['label' => 'weather.type.14', 'icon' => 'bi-cloud-rain-heavy'],
        15 => ['label' => 'weather.type.15', 'icon' => 'bi-cloud-drizzle'],
        16 => ['label' => 'weather.type.16', 'icon' => 'bi-cloud-fog2'],
        17 => ['label' => 'weather.type.17', 'icon' => 'bi-cloud-fog'],
        18 => ['label' => 'weather.type.18', 'icon' => 'bi-cloud-snow'],
        19 => ['label' => 'weather.type.19', 'icon' => 'bi-cloud-lightning'],
        20 => ['label' => 'weather.type.20', 'icon' => 'bi-cloud-lightning-rain'],
        21 => ['label' => 'weather.type.21', 'icon' => 'bi-cloud-hail'],
        22 => ['label' => 'weather.type.22', 'icon' => 'bi-snow'],
        23 => ['label' => 'weather.type.23', 'icon' => 'bi-cloud-lightning-rain'],
        24 => ['label' => 'weather.type.24', 'icon' => 'bi-clouds'],
        25 => ['label' => 'weather.type.25', 'icon' => 'bi-cloud-sun'],
        26 => ['label' => 'weather.type.26', 'icon' => 'bi-cloud-fog'],
        27 => ['label' => 'weather.type.27', 'icon' => 'bi-clouds'],
        28 => ['label' => 'weather.type.28', 'icon' => 'bi-cloud-snow'],
        29 => ['label' => 'weather.type.29', 'icon' => 'bi-cloud-sleet'],
        30 => ['label' => 'weather.type.30', 'icon' => 'bi-cloud-rain'],
    ];

    /**
     * @var array<int, string>
     */
    private const WIND_SPEED_CLASSES = [
        1 => 'weather.wind_speed.1',
        2 => 'weather.wind_speed.2',
        3 => 'weather.wind_speed.3',
        4 => 'weather.wind_speed.4',
    ];

    /**
     * @var array<int, string>
     */
    private const PRECIPITATION_CLASSES = [
        1 => 'weather.precipitation.1',
        2 => 'weather.precipitation.2',
        3 => 'weather.precipitation.3',
    ];

    /**
     * Returns a translation key; resolve with `|trans` in Twig.
     */
    public static function weatherTypeLabel(int $id): string
    {
        return self::WEATHER_TYPES[$id]['label'] ?? 'weather.type.unknown';
    }

    public static function weatherTypeIcon(int $id): string
    {
        return self::WEATHER_TYPES[$id]['icon'] ?? 'bi-question-circle';
    }

    /**
     * Returns a translation key; resolve with `|trans` in Twig.
     */
    public static function windSpeedLabel(int $classId): string
    {
        return self::WIND_SPEED_CLASSES[$classId] ?? 'weather.wind_speed.unknown';
    }

    /**
     * Returns a translation key or null; resolve with `|trans` in Twig.
     */
    public static function precipitationLabel(?int $classId): ?string
    {
        if ($classId === null) {
            return null;
        }

        return self::PRECIPITATION_CLASSES[$classId] ?? 'weather.precipitation.unknown';
    }

    /**
     * Returns a translation key for a compass direction code; resolve with `|trans` in Twig.
     */
    public static function windDirectionLabel(string $code): string
    {
        $code = strtoupper(trim($code));

        return match ($code) {
            'N'  => 'wind.direction.n',
            'NE' => 'wind.direction.ne',
            'E'  => 'wind.direction.e',
            'SE' => 'wind.direction.se',
            'S'  => 'wind.direction.s',
            'SW' => 'wind.direction.sw',
            'W'  => 'wind.direction.w',
            'NW' => 'wind.direction.nw',
            default => $code !== '' ? $code : 'wind.direction.variable',
        };
    }
}
