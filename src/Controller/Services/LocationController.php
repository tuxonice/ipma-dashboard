<?php

declare(strict_types=1);

namespace App\Controller\Services;

use App\Service\Forecast\Warnings\AwarenessLevel;
use App\Service\Forecast\Warnings\WarningRepository;
use App\Service\ForecastRepository;
use App\Service\Services\LocationRepository;
use App\Service\WeatherDictionary;
use DateTimeImmutable;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tlab\IpmaApi\Exception\IpmaApiException;
use Twig\Environment;

/**
 * Location browsing controller.
 *
 * - `index`: lists every district/island location grouped by region.
 * - `show`: renders the details for a single location (placeholder for the
 *   forecast view that will land in the next milestone).
 */
final class LocationController
{
    public function __construct(
        private readonly Environment $twig,
        private readonly LocationRepository $locations,
        private readonly ForecastRepository $forecasts,
        private readonly WarningRepository $warnings,
    ) {
    }

    public function index(): Response
    {
        try {
            $grouped = $this->locations->groupedByRegion();
            $error = null;
        } catch (IpmaApiException $e) {
            $grouped = [];
            $error = $e->getMessage();
        }

        $regionLabels = [];
        foreach (array_keys($grouped) as $regionId) {
            $regionLabels[$regionId] = LocationRepository::regionLabel($regionId);
        }

        $html = $this->twig->render('Services/location.index.html.twig', [
            'grouped' => $grouped,
            'region_labels' => $regionLabels,
            'error' => $error,
        ]);

        return new Response($html);
    }

    public function show(int $globalIdLocal, Request $request): Response
    {
        try {
            $location = $this->locations->findByGlobalId($globalIdLocal);
        } catch (IpmaApiException $e) {
            $html = $this->twig->render('Services/location.show.html.twig', [
                'location' => null,
                'timezone' => 'Europe/Lisbon',
                'error' => $e->getMessage(),
                'forecast_days' => [],
                'forecast_updated_at' => null,
                'forecast_error' => null,
                'area_warnings' => [],
            ]);

            return new Response($html, Response::HTTP_BAD_GATEWAY);
        }

        if ($location === null) {
            throw new NotFoundHttpException(sprintf('Location %d not found.', $globalIdLocal));
        }

        $forecastDays = [];
        $forecastUpdatedAt = null;
        $forecastError = null;

        try {
            $forecast = $this->forecasts->dailyForecast($globalIdLocal);
            $forecastUpdatedAt = $forecast->updatedAt;
            $forecastDays = array_map(
                static function ($record) {
                    $date = new DateTimeImmutable($record->forecastDate);

                    return [
                        'date' => $date,
                        'weather_type_id' => $record->idWeatherType,
                        'weather_type_label' => WeatherDictionary::weatherTypeLabel($record->idWeatherType),
                        'weather_type_icon' => WeatherDictionary::weatherTypeIcon($record->idWeatherType),
                        'min_temp' => $record->minTemp,
                        'max_temp' => $record->maxTemp,
                        'rainfall_prob' => $record->rainfallProb,
                        'rain_intensity_label' => WeatherDictionary::precipitationLabel($record->rainfallIntensity),
                        'wind_dir' => $record->winDir,
                        'wind_dir_label' => WeatherDictionary::windDirectionLabel($record->winDir),
                        'wind_speed_class' => $record->windSpeedClass,
                        'wind_speed_label' => WeatherDictionary::windSpeedLabel($record->windSpeedClass),
                    ];
                },
                $forecast->records,
            );
        } catch (IpmaApiException $e) {
            $forecastError = $e->getMessage();
        }

        $tz = new \DateTimeZone(LocationRepository::regionTimezone($location->idRegion));
        $areaWarnings = [];
        try {
            foreach ($this->warnings->forArea($location->idWarningArea) as $warning) {
                $areaWarnings[] = [
                    'warning' => $warning,
                    'level' => AwarenessLevel::meta($warning->awarenessLevelID),
                    'timezone' => $tz->getName(),
                ];
            }
        } catch (IpmaApiException) {
            // Warnings are non-critical — ignore upstream errors here so the
            // forecast view still renders. (The /warnings page surfaces errors.)
        }

        $html = $this->twig->render('Services/location.show.html.twig', [
            'location' => $location,
            'region_label' => LocationRepository::regionLabel($location->idRegion),
            'timezone' => $tz->getName(),
            'error' => null,
            'forecast_days' => $forecastDays,
            'forecast_updated_at' => $forecastUpdatedAt instanceof \DateTimeImmutable ? $forecastUpdatedAt->setTimezone($tz) : null,
            'forecast_error' => $forecastError,
            'area_warnings' => $areaWarnings,
        ]);

        return new Response($html);
    }
}
