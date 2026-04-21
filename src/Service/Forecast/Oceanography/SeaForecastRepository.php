<?php

declare(strict_types=1);

namespace App\Service\Forecast\Oceanography;

use DateTimeImmutable;
use Psr\SimpleCache\CacheInterface;
use Tlab\IpmaApi\Enums\SeaStateForecastDayEnum;
use Tlab\IpmaApi\Exception\IpmaApiException;
use Tlab\IpmaApi\IpmaForecast;

/**
 * Assembles a 3-day sea-state forecast for a given coastal location.
 *
 * IPMA publishes the sea-state forecast as three separate daily files
 * (today, tomorrow, the day after). We fetch each one and pick out the
 * record matching the requested `globalIdLocal`. Missing per-day records
 * are silently skipped — the UI shows whatever days are available.
 */
final class SeaForecastRepository
{
    public function __construct(private readonly CacheInterface $cache)
    {
    }

    /**
     * @return list<SeaStateDay>
     */
    public function forLocation(int $globalIdLocal): array
    {
        $days = [];

        foreach (SeaStateForecastDayEnum::cases() as $day) {
            try {
                $api = IpmaForecast::createSeaStateForecastApi($this->cache)
                    ->from($day)
                    ->filterByGlobalIdLocal($globalIdLocal);
            } catch (IpmaApiException) {
                // Skip individual days that fail upstream; keep the rest.
                continue;
            }

            $records = $api->get();
            if ($records === []) {
                continue;
            }

            $days[] = new SeaStateDay(
                forecastDate: DateTimeImmutable::createFromMutable($api->getForecastDate()),
                updatedAt: DateTimeImmutable::createFromMutable($api->getUpdateAt()),
                record: $records[0],
            );
        }

        return $days;
    }
}
