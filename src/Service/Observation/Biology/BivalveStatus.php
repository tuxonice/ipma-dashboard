<?php

declare(strict_types=1);

namespace App\Service\Observation\Biology;

/**
 * Helper for IPMA's bivalve mollusc harvesting interdiction status.
 *
 * Observed values on the `CI_SNMB.geojson` feed:
 *
 *   OPEN            All listed species may be harvested.
 *   PARTIAL_OPEN    Some species are open, some are interdicted.
 *   PARTIAL_CLOSE   Most species interdicted, a few still open.
 *   CLOSE           All listed species interdicted.
 *
 * Anything else is treated as "Unknown" so the helper degrades gracefully
 * if IPMA introduces a new status without breaking the page.
 */
final class BivalveStatus
{
    public const OPEN = 'OPEN';
    public const PARTIAL_OPEN = 'PARTIAL_OPEN';
    public const PARTIAL_CLOSE = 'PARTIAL_CLOSE';
    public const CLOSE = 'CLOSE';
    public const UNKNOWN = 'UNKNOWN';

    /** @var array<string, array{label: string, color: string, text: string, bootstrap: string}> */
    private const MAP = [
        self::OPEN          => ['label' => 'bivalves.status.open',           'color' => '#198754', 'text' => '#ffffff', 'bootstrap' => 'success'],
        self::PARTIAL_OPEN  => ['label' => 'bivalves.status.partial_open',   'color' => '#ffc107', 'text' => '#212529', 'bootstrap' => 'warning'],
        self::PARTIAL_CLOSE => ['label' => 'bivalves.status.partial_close',  'color' => '#fd7e14', 'text' => '#212529', 'bootstrap' => 'warning'],
        self::CLOSE         => ['label' => 'bivalves.status.close',          'color' => '#dc3545', 'text' => '#ffffff', 'bootstrap' => 'danger'],
        self::UNKNOWN       => ['label' => 'bivalves.status.unknown',        'color' => '#adb5bd', 'text' => '#212529', 'bootstrap' => 'secondary'],
    ];

    public static function normalise(string $status): string
    {
        $upper = strtoupper(trim($status));

        return isset(self::MAP[$upper]) ? $upper : self::UNKNOWN;
    }

    public static function label(string $status): string
    {
        return self::MAP[self::normalise($status)]['label'];
    }

    public static function color(string $status): string
    {
        return self::MAP[self::normalise($status)]['color'];
    }

    public static function textColor(string $status): string
    {
        return self::MAP[self::normalise($status)]['text'];
    }

    public static function bootstrap(string $status): string
    {
        return self::MAP[self::normalise($status)]['bootstrap'];
    }

    /**
     * Statuses in display / severity order (most permissive first).
     *
     * @return list<string>
     */
    public static function all(): array
    {
        return [self::OPEN, self::PARTIAL_OPEN, self::PARTIAL_CLOSE, self::CLOSE];
    }
}
