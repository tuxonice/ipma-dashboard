<?php

declare(strict_types=1);

namespace App\Service;

use DateTimeImmutable;
use Tlab\IpmaApi\ApiConnectorInterface;
use Tlab\IpmaApi\Dto\Forecast\DailyForecastByDayRecord;
use Tlab\IpmaApi\Endpoints;
use Tlab\IpmaApi\Enums\ForecastDayEnum;

/**
 * Repository over IPMA's "daily forecast aggregated by day" endpoint
 * (`/forecast/meteorology/cities/daily/hp-daily-forecast-day{idDay}.json`).
 *
 * Returns the Portugal-wide outlook for a single forecast day: every
 * IPMA location at once with min/max temperature, weather type, wind
 * and rainfall probability.
 *
 * As of `tuxonice/ipma-api` `dev-ipm-00-require-cache` this endpoint is
 * no longer wrapped by a dedicated factory class, so we hit the cached
 * {@see ApiConnectorInterface} directly and map the raw payload into
 * the DTO shape this dashboard already consumes.
 */
final class OutlookRepository
{
    private const ENDPOINT = Endpoints::BASE_URL . '/forecast/meteorology/cities/daily/hp-daily-forecast-day{idDay}.json';

    public function __construct(private readonly ApiConnectorInterface $apiConnector)
    {
    }

    /**
     * @return array{
     *     records: list<DailyForecastByDayRecord>,
     *     update_at: ?DateTimeImmutable,
     * }
     */
    public function forDay(ForecastDayEnum $day): array
    {
        $url = str_replace('{idDay}', (string) $day->value, self::ENDPOINT);

        /** @var array{data?: list<array<string, mixed>>, dataUpdate?: string} $payload */
        $payload = $this->apiConnector->fetchData($url);

        $records = [];
        foreach ($payload['data'] ?? [] as $row) {
            $records[] = $this->hydrate($row);
        }

        return [
            'records' => $records,
            'update_at' => $this->parseDate($payload['dataUpdate'] ?? null),
        ];
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrate(array $row): DailyForecastByDayRecord
    {
        $rainfallIntensity = $row['classPrecInt'] ?? null;

        return new DailyForecastByDayRecord(
            globalIdLocal: (int) $row['globalIdLocal'],
            idWeatherType: (int) $row['idWeatherType'],
            windSpeedClass: (int) $row['classWindSpeed'],
            rainfallIntensity: $rainfallIntensity !== null ? (int) $rainfallIntensity : null,
            rainfallProb: (float) ($row['probabilityOfPrecipitation'] ?? 0),
            minTemp: (float) $row['tMin'],
            maxTemp: (float) $row['tMax'],
            winDir: (string) $row['predWindDir'],
            latitude: (float) $row['latitude'],
            longitude: (float) $row['longitude'],
        );
    }

    private function parseDate(?string $value): ?DateTimeImmutable
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            return new DateTimeImmutable($value, new \DateTimeZone('UTC'));
        } catch (\Exception) {
            return null;
        }
    }
}
