<?php

declare(strict_types=1);

namespace App\Controller\Forecast\Warnings;

use App\Service\Forecast\Warnings\AwarenessLevel;
use App\Service\Forecast\Warnings\WarningRepository;
use App\Service\Services\LocationRepository;
use Symfony\Component\HttpFoundation\Response;
use Tlab\IpmaApi\Dto\Forecast\WeatherWarning;
use Tlab\IpmaApi\Exception\IpmaApiException;
use Twig\Environment;

/**
 * Lists active meteorological warnings published by IPMA.
 */
final class WarningController
{
    private const MISSING_WARNING_LOCATION_NAME = [
        'MCN' => 'Madeira-Costa Norte',
        'MRM' => 'Madeira-R. Montanhosas',
    ];


    public function __construct(
        private readonly Environment $twig,
        private readonly WarningRepository $warnings,
        private readonly LocationRepository $locations,
    ) {
    }

    public function index(): Response
    {
        $grouped = [];
        $error = null;

        try {
            $grouped = $this->warnings->activeGroupedByArea();
        } catch (IpmaApiException $e) {
            $error = $e->getMessage();
        }

        // Decorate each warning with its level metadata and timezone for the template.
        $decorated = [];
        foreach ($grouped as $area => $warnings) {
            $location = $this->locations->findByIdWarningArea($area);
            $locationName = $location->name ?? self::MISSING_WARNING_LOCATION_NAME[$area] ?? $area;
            $timezone = LocationRepository::regionTimezone($location->idRegion ?? 1);
            $decorated[$locationName] = array_map(
                static fn(WeatherWarning $w) => [
                    'warning' => $w,
                    'level' => AwarenessLevel::meta($w->awarenessLevelID),
                    'timezone' => $timezone,
                ],
                $warnings,
            );
        }

        $html = $this->twig->render('Forecast/Warnings/warning.index.html.twig', [
            'grouped' => $decorated,
            'error' => $error,
        ]);

        return new Response($html);
    }
}
