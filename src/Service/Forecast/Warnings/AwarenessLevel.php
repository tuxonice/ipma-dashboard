<?php

declare(strict_types=1);

namespace App\Service\Forecast\Warnings;

/**
 * Helper for IPMA meteorological awareness levels (MeteoAlarm convention).
 *
 * IPMA returns `awarenessLevelID` as a lowercase colour name. We map each
 * level to a human-readable label, severity rank (for sorting), Bootstrap
 * contextual colour, and a matching Bootstrap Icon.
 */
final class AwarenessLevel
{
    public const GREEN  = 'green';
    public const YELLOW = 'yellow';
    public const ORANGE = 'orange';
    public const RED    = 'red';

    private const META = [
        self::GREEN  => ['label' => 'awareness.level.green',  'severity' => 0, 'color' => 'success', 'icon' => 'bi-shield-check'],
        self::YELLOW => ['label' => 'awareness.level.yellow', 'severity' => 1, 'color' => 'yellow', 'icon' => 'bi-exclamation-triangle'],
        self::ORANGE => ['label' => 'awareness.level.orange', 'severity' => 2, 'color' => 'warning', 'icon' => 'bi-exclamation-triangle-fill'],
        self::RED    => ['label' => 'awareness.level.red',    'severity' => 3, 'color' => 'danger',  'icon' => 'bi-exclamation-octagon-fill'],
    ];

    /**
     * @return array{label: string, severity: int, color: string, icon: string}
     *
     * `label` is a translation key; resolve with `|trans` in Twig.
     */
    public static function meta(string $levelId): array
    {
        $key = strtolower(trim($levelId));

        return self::META[$key] ?? [
            'label' => 'awareness.level.unknown',
            'severity' => -1,
            'color' => 'secondary',
            'icon' => 'bi-question-circle',
        ];
    }

    public static function severity(string $levelId): int
    {
        return self::meta($levelId)['severity'];
    }

    public static function isActive(string $levelId): bool
    {
        return self::severity($levelId) > self::META[self::GREEN]['severity'];
    }
}
