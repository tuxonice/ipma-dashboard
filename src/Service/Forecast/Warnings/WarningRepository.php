<?php

declare(strict_types=1);

namespace App\Service\Forecast\Warnings;

use Psr\SimpleCache\CacheInterface;
use Tlab\IpmaApi\Dto\Forecast\WeatherWarning;
use Tlab\IpmaApi\IpmaForecast;

/**
 * Repository over the IPMA weather-warnings endpoint.
 *
 * Wraps the `tuxonice/ipma-api` client with convenience methods for the
 * two views needed by the dashboard:
 *
 *  - `activeGroupedByArea()` → warnings index page.
 *  - `forArea()`             → banner on the location detail page.
 *
 * "Active" means any awareness level above green. Warnings are returned
 * sorted by severity (descending) then start time.
 */
final class WarningRepository
{
    public function __construct(private readonly CacheInterface $cache)
    {
    }

    /**
     * @return list<WeatherWarning>
     */
    public function all(): array
    {
        return IpmaForecast::createWeatherWarningsApi($this->cache)
            ->query()
            ->get();
    }

    /**
     * @return list<WeatherWarning>
     */
    public function active(): array
    {
        $warnings = array_values(array_filter(
            $this->all(),
            static fn(WeatherWarning $w) => AwarenessLevel::isActive($w->awarenessLevelID),
        ));

        self::sortBySeverity($warnings);

        return $warnings;
    }

    /**
     * Active warnings for a specific IPMA warning area (e.g. "LSB", "PTG").
     *
     * @return list<WeatherWarning>
     */
    public function forArea(string $idArea): array
    {
        $warnings = array_values(array_filter(
            $this->all(),
            static fn(WeatherWarning $w)
                => $w->warningIdArea === $idArea
                && AwarenessLevel::isActive($w->awarenessLevelID),
        ));

        self::sortBySeverity($warnings);

        return $warnings;
    }

    /**
     * @return array<string, list<WeatherWarning>> Keyed by warning area id.
     */
    public function activeGroupedByArea(): array
    {
        $grouped = [];
        foreach ($this->active() as $warning) {
            $grouped[$warning->warningIdArea][] = $warning;
        }

        ksort($grouped);

        return $grouped;
    }

    /**
     * @param list<WeatherWarning> $warnings
     */
    private static function sortBySeverity(array &$warnings): void
    {
        usort(
            $warnings,
            static function (WeatherWarning $a, WeatherWarning $b): int {
                $cmp = AwarenessLevel::severity($b->awarenessLevelID)
                    <=> AwarenessLevel::severity($a->awarenessLevelID);
                if ($cmp !== 0) {
                    return $cmp;
                }

                return strcmp($a->startTime, $b->startTime);
            },
        );
    }
}
