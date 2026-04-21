<?php

declare(strict_types=1);

namespace App\Service\Observation\Seismic;

use DateTimeImmutable;
use Psr\SimpleCache\CacheInterface;
use Tlab\IpmaApi\Dto\Seismic\SeismicEvent;
use Tlab\IpmaApi\Enums\SeismicInformationAreaEnum;
use Tlab\IpmaApi\IpmaObservation;

/**
 * Repository over IPMA's seismic information endpoint
 * (`/observation/seismic/{idArea}.json`).
 *
 * Exposes the most recent events for a given area together with the
 * upstream metadata (last seismic activity timestamp + feed update).
 */
final class SeismicRepository
{
    public function __construct(private readonly CacheInterface $cache)
    {
    }

    /**
     * @return array{
     *     events: list<SeismicEvent>,
     *     last_activity: ?DateTimeImmutable,
     *     update_date: ?DateTimeImmutable,
     * }
     */
    public function forArea(SeismicInformationAreaEnum $area): array
    {
        $api = IpmaObservation::createSeismicInformationApi($this->cache)->from($area);

        $events = $api->get();
        usort(
            $events,
            static fn(SeismicEvent $a, SeismicEvent $b) => strcmp($b->time, $a->time),
        );

        return [
            'events' => $events,
            'last_activity' => DateTimeImmutable::createFromMutable($api->getLastSeismicActivityDate()),
            'update_date' => DateTimeImmutable::createFromMutable($api->getUpdateDate()),
        ];
    }
}
