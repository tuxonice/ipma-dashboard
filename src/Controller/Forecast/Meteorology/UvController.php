<?php

declare(strict_types=1);

namespace App\Controller\Forecast\Meteorology;

use App\Service\Forecast\Meteorology\UvIndexLevel;
use App\Service\Forecast\Meteorology\UvIndexRepository;
use App\Service\Services\LocationRepository;
use Symfony\Component\HttpFoundation\Response;
use Tlab\IpmaApi\Exception\IpmaApiException;
use Twig\Environment;

/**
 * Ultraviolet (UV) risk forecast view.
 *
 * Renders the 3-day per-location UV index table grouped by region, plus
 * a summary legend with WHO bands.
 */
final class UvController
{
    public function __construct(
        private readonly Environment $twig,
        private readonly LocationRepository $locations,
        private readonly UvIndexRepository $uvIndex,
    ) {
    }

    public function index(): Response
    {
        $error = null;
        $dates = [];
        $regions = [];
        $counts = array_fill_keys(UvIndexLevel::all(), 0);
        $timeInterval = null;

        try {
            $grouped = $this->uvIndex->groupedByLocation();
            $dates = $this->uvIndex->forecastDates();
            $locationsByRegion = $this->locations->groupedByRegion();

            foreach ($locationsByRegion as $regionId => $locations) {
                $rows = [];
                foreach ($locations as $location) {
                    $records = $grouped[$location->globalIdLocal] ?? [];
                    if ($records === []) {
                        continue;
                    }

                    $byDate = [];
                    foreach ($records as $record) {
                        $byDate[$record->forecastDate] = $record;
                        if ($timeInterval === null && $record->timeInterval !== '') {
                            $timeInterval = $record->timeInterval;
                        }
                    }

                    $cells = [];
                    foreach ($dates as $date) {
                        $record = $byDate[$date] ?? null;
                        if ($record === null) {
                            $cells[] = null;
                            continue;
                        }

                        $level = UvIndexLevel::fromUvIndex($record->uvIndex);
                        $counts[$level]++;

                        $cells[] = [
                            'uv' => $record->uvIndex,
                            'level' => $level,
                            'level_label' => UvIndexLevel::label($level),
                            'color' => UvIndexLevel::color($level),
                            'text' => UvIndexLevel::textColor($level),
                        ];
                    }

                    $rows[] = [
                        'global_id_local' => $location->globalIdLocal,
                        'name' => $location->name,
                        'cells' => $cells,
                    ];
                }

                if ($rows === []) {
                    continue;
                }

                $regions[] = [
                    'id' => $regionId,
                    'label' => LocationRepository::regionLabel($regionId),
                    'rows' => $rows,
                ];
            }
        } catch (IpmaApiException $e) {
            $error = $e->getMessage();
        }

        $legend = [];
        foreach (UvIndexLevel::all() as $level) {
            $legend[] = [
                'level' => $level,
                'label' => UvIndexLevel::label($level),
                'color' => UvIndexLevel::color($level),
                'text' => UvIndexLevel::textColor($level),
                'bootstrap' => UvIndexLevel::bootstrap($level),
                'advice' => UvIndexLevel::advice($level),
                'count' => $counts[$level],
            ];
        }

        $html = $this->twig->render('Forecast/Meteorology/uv.index.html.twig', [
            'error' => $error,
            'dates' => $dates,
            'regions' => $regions,
            'legend' => $legend,
            'time_interval' => $timeInterval,
        ]);

        return new Response($html);
    }
}
