<?php

declare(strict_types=1);

namespace App\Service\Forecast\Oceanography;

use DateTimeInterface;
use Tlab\IpmaApi\Dto\Forecast\SeaStateRecord;

/**
 * Immutable value object bundling a single day's sea-state record for a
 * coastal location together with the forecast date and upstream file update
 * time.
 */
final class SeaStateDay
{
    public function __construct(
        public readonly DateTimeInterface $forecastDate,
        public readonly DateTimeInterface $updatedAt,
        public readonly SeaStateRecord $record,
    ) {
    }
}
