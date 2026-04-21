<?php

declare(strict_types=1);

namespace App\Service;

use DateTimeImmutable;
use Psr\SimpleCache\CacheInterface;
use Tlab\IpmaApi\Dto\Forecast\DailyForecastByLocationRecord;
use Tlab\IpmaApi\IpmaForecast;

/**
 * Thin repository over the IPMA daily-forecast-by-location endpoint.
 *
 * Returns forecasts ordered by date along with the upstream file's
 * `dataUpdate` timestamp so the UI can show freshness information.
 */
final class ForecastRepository
{
    public function __construct(private readonly CacheInterface $cache)
    {
    }

    public function dailyForecast(int $globalIdLocal): DailyForecast
    {
        $api = IpmaForecast::createDailyWeatherForecastByLocalApi($this->cache)
            ->from($globalIdLocal);

        $records = $api->get();

        // Sort by forecastDate ascending (the API already does this, but be defensive).
        usort(
            $records,
            static fn(DailyForecastByLocationRecord $a, DailyForecastByLocationRecord $b)
                => strcmp($a->forecastDate, $b->forecastDate),
        );

        $updatedAt = DateTimeImmutable::createFromMutable($api->getFileUpdatedAt());

        return new DailyForecast($records, $updatedAt);
    }
}
