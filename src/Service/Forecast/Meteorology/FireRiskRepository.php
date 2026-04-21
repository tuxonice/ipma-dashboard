<?php

declare(strict_types=1);

namespace App\Service\Forecast\Meteorology;

use DateTimeImmutable;
use Psr\SimpleCache\CacheInterface;
use Tlab\IpmaApi\Dto\Forecast\FireRiskRecord;
use Tlab\IpmaApi\Enums\ForecastFireRiskDayEnum;
use Tlab\IpmaApi\IpmaForecast;

/**
 * Repository over IPMA's fire-risk forecast (RCM) endpoint.
 *
 * Returns the list of {@see FireRiskRecord}s published for a given day
 * (today or tomorrow), together with the metadata the upstream file
 * exposes — `fileDate`, `dataPrev` (forecast target), and `dataRun`
 * (model run).
 */
final class FireRiskRepository
{
    public function __construct(private readonly CacheInterface $cache)
    {
    }

    /**
     * @return array{
     *     records: list<FireRiskRecord>,
     *     forecast_date: ?DateTimeImmutable,
     *     run_date: ?DateTimeImmutable,
     *     file_date: ?DateTimeImmutable,
     * }
     */
    public function forDay(ForecastFireRiskDayEnum $day): array
    {
        $api = IpmaForecast::createFireRiskForecastApi($this->cache)->from($day);

        return [
            'records' => $api->get(),
            'forecast_date' => DateTimeImmutable::createFromMutable($api->getForecastDate()),
            'run_date' => DateTimeImmutable::createFromMutable($api->getRunDate()),
            'file_date' => DateTimeImmutable::createFromMutable($api->getFileUpdatedAt()),
        ];
    }
}
