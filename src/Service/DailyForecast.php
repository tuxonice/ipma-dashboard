<?php

declare(strict_types=1);

namespace App\Service;

use DateTimeInterface;
use Tlab\IpmaApi\Dto\Forecast\DailyForecastByLocationRecord;

/**
 * Immutable value object bundling a location's daily forecast records with
 * the upstream file's update timestamp (for data-freshness display).
 */
final class DailyForecast
{
    /**
     * @param list<DailyForecastByLocationRecord> $records
     */
    public function __construct(
        public readonly array $records,
        public readonly DateTimeInterface $updatedAt,
    ) {
    }

    public function isEmpty(): bool
    {
        return $this->records === [];
    }
}
