<?php

declare(strict_types=1);

namespace App\Service;

/**
 * Maps a temperature value (°C) to a colour band for use on the
 * Portugal-wide outlook map / table.
 *
 * The bands are deliberately simple and broad — they exist purely for
 * map-marker colouring, not for any meteorological classification.
 */
final class TemperatureBand
{
    /**
     * @return array{label: string, color: string, text: string}
     */
    public static function classify(float $celsius): array
    {
        return match (true) {
            $celsius < 0   => ['label' => 'Below 0',  'color' => '#1d4ed8', 'text' => '#ffffff'],
            $celsius < 10  => ['label' => '0–9',      'color' => '#3b82f6', 'text' => '#ffffff'],
            $celsius < 15  => ['label' => '10–14',    'color' => '#06b6d4', 'text' => '#212529'],
            $celsius < 20  => ['label' => '15–19',    'color' => '#10b981', 'text' => '#212529'],
            $celsius < 25  => ['label' => '20–24',    'color' => '#facc15', 'text' => '#212529'],
            $celsius < 30  => ['label' => '25–29',    'color' => '#f59e0b', 'text' => '#212529'],
            $celsius < 35  => ['label' => '30–34',    'color' => '#ef4444', 'text' => '#ffffff'],
            $celsius < 40  => ['label' => '35–39',    'color' => '#b91c1c', 'text' => '#ffffff'],
            default        => ['label' => '40+',      'color' => '#7f1d1d', 'text' => '#ffffff'],
        };
    }

    /**
     * Bands in ascending order, suitable for legends.
     *
     * @return list<array{label: string, color: string, text: string}>
     */
    public static function bands(): array
    {
        return [
            self::classify(-1),
            self::classify(5),
            self::classify(12),
            self::classify(17),
            self::classify(22),
            self::classify(27),
            self::classify(32),
            self::classify(37),
            self::classify(40),
        ];
    }
}
