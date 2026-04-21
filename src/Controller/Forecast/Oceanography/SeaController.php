<?php

declare(strict_types=1);

namespace App\Controller\Forecast\Oceanography;

use App\Service\Forecast\Oceanography\SeaForecastRepository;
use App\Service\Services\LocationRepository;
use App\Service\Services\SeaLocationRepository;
use App\Service\WeatherDictionary;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tlab\IpmaApi\Exception\IpmaApiException;
use Twig\Environment;

/**
 * Sea-state browsing controller.
 *
 * - `index`: lists every coastal location grouped by region.
 * - `show`: renders a 3-day sea-state forecast (wave, swell, SST) for one
 *   coastal location.
 */
final class SeaController
{
    public function __construct(
        private readonly Environment $twig,
        private readonly SeaLocationRepository $seaLocations,
        private readonly SeaForecastRepository $seaForecasts,
    ) {
    }

    public function index(): Response
    {
        try {
            $grouped = $this->seaLocations->groupedByRegion();
            $error = null;
        } catch (IpmaApiException $e) {
            $grouped = [];
            $error = $e->getMessage();
        }

        $regionLabels = [];
        foreach (array_keys($grouped) as $regionId) {
            $regionLabels[$regionId] = LocationRepository::regionLabel($regionId);
        }

        $html = $this->twig->render('Forecast/Oceanography/sea.index.html.twig', [
            'grouped' => $grouped,
            'region_labels' => $regionLabels,
            'error' => $error,
        ]);

        return new Response($html);
    }

    public function show(int $globalIdLocal): Response
    {
        try {
            $location = $this->seaLocations->findByGlobalId($globalIdLocal);
        } catch (IpmaApiException $e) {
            return new Response(
                $this->twig->render('Forecast/Oceanography/sea.show.html.twig', [
                    'location' => null,
                    'timezone' => 'Europe/Lisbon',
                    'error' => $e->getMessage(),
                    'days' => [],
                    'updated_at' => null,
                ]),
                Response::HTTP_BAD_GATEWAY,
            );
        }

        if ($location === null) {
            throw new NotFoundHttpException(sprintf('Sea location %d not found.', $globalIdLocal));
        }

        $days = [];
        $updatedAt = null;
        $forecastError = null;

        try {
            foreach ($this->seaForecasts->forLocation($globalIdLocal) as $day) {
                $record = $day->record;
                $updatedAt = $day->updatedAt;
                $days[] = [
                    'forecast_date' => $day->forecastDate,
                    'wave_dir' => $record->predWaveDir,
                    'wave_dir_label' => WeatherDictionary::windDirectionLabel($record->predWaveDir),
                    'wave_high_min' => $record->waveHighMin,
                    'wave_high_max' => $record->waveHighMax,
                    'wave_period_min' => $record->wavePeriodMin,
                    'wave_period_max' => $record->wavePeriodMax,
                    'total_sea_min' => $record->totalSeaMin,
                    'total_sea_max' => $record->totalSeaMax,
                    'sst_min' => $record->sstMin,
                    'sst_max' => $record->sstMax,
                ];
            }
        } catch (IpmaApiException $e) {
            $forecastError = $e->getMessage();
        }

        $tz = new \DateTimeZone(LocationRepository::regionTimezone($location->idRegion));

        $html = $this->twig->render('Forecast/Oceanography/sea.show.html.twig', [
            'location' => $location,
            'region_label' => LocationRepository::regionLabel($location->idRegion),
            'timezone' => $tz->getName(),
            'days' => $days,
            'updated_at' => $updatedAt instanceof \DateTimeImmutable ? $updatedAt->setTimezone($tz) : null,
            'error' => null,
            'forecast_error' => $forecastError,
        ]);

        return new Response($html);
    }
}
