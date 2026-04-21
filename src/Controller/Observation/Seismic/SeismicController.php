<?php

declare(strict_types=1);

namespace App\Controller\Observation\Seismic;

use App\Service\Observation\Seismic\SeismicMagnitudeLevel;
use App\Service\Observation\Seismic\SeismicRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Tlab\IpmaApi\Enums\SeismicInformationAreaEnum;
use Tlab\IpmaApi\Exception\IpmaApiException;
use Twig\Environment;

/**
 * Seismic information view.
 *
 * Lists the most recent earthquake events published by IPMA for the
 * selected area (mainland Portugal + Madeira, or the Azores) on a
 * Leaflet map plus a sortable table.
 */
final class SeismicController
{
    private const AREAS = [
        'mainland' => ['enum' => SeismicInformationAreaEnum::MAIN_LAND_AND_MADEIRA, 'label' => 'seismic.area.mainland'],
        'azores'   => ['enum' => SeismicInformationAreaEnum::AZORES,                'label' => 'seismic.area.azores'],
    ];

    public function __construct(
        private readonly Environment $twig,
        private readonly SeismicRepository $seismic,
    ) {
    }

    public function index(Request $request): Response
    {
        $areaParam = strtolower((string) $request->query->get('area', 'mainland'));
        if (!isset(self::AREAS[$areaParam])) {
            $areaParam = 'mainland';
        }

        $events = [];
        $features = [];
        $counts = array_fill_keys(SeismicMagnitudeLevel::all(), 0);
        $unknownCount = 0;
        $lastActivity = null;
        $updateDate = null;
        $error = null;

        try {
            $result = $this->seismic->forArea(self::AREAS[$areaParam]['enum']);
            $lastActivity = $result['last_activity'];
            $updateDate = $result['update_date'];

            foreach ($result['events'] as $event) {
                $level = SeismicMagnitudeLevel::fromMagnitude($event->magnitude);
                $reported = SeismicMagnitudeLevel::isReported($event->magnitude);

                if ($level === SeismicMagnitudeLevel::UNKNOWN) {
                    $unknownCount++;
                } else {
                    $counts[$level]++;
                }

                $events[] = [
                    'seism_id' => $event->seismId,
                    'time' => $event->time,
                    'region' => $event->regionName,
                    'location' => $event->location,
                    'magnitude' => $reported ? $event->magnitude : null,
                    'mag_type' => $event->magType,
                    'depth' => $event->depth,
                    'lat' => $event->latitude,
                    'lng' => $event->longitude,
                    'sensed' => $event->sensed,
                    'level' => $level,
                    'level_label' => SeismicMagnitudeLevel::label($level),
                    'color' => SeismicMagnitudeLevel::color($level),
                ];

                $features[] = [
                    'lat' => $event->latitude,
                    'lng' => $event->longitude,
                    'magnitude' => $reported ? $event->magnitude : null,
                    'mag_type' => $event->magType,
                    'depth' => $event->depth,
                    'time' => $event->time,
                    'region' => $event->regionName,
                    'level' => $level,
                    'level_label' => SeismicMagnitudeLevel::label($level),
                    'color' => SeismicMagnitudeLevel::color($level),
                    'radius' => SeismicMagnitudeLevel::radius($level),
                ];
            }
        } catch (IpmaApiException $e) {
            $error = $e->getMessage();
        } catch (\Throwable $e) {
            $error = $e->getMessage();
        }

        $legend = [];
        foreach (SeismicMagnitudeLevel::all() as $level) {
            $legend[] = [
                'level' => $level,
                'label' => SeismicMagnitudeLevel::label($level),
                'color' => SeismicMagnitudeLevel::color($level),
                'bootstrap' => SeismicMagnitudeLevel::bootstrap($level),
                'count' => $counts[$level],
            ];
        }

        $areaTabs = [];
        foreach (self::AREAS as $key => $meta) {
            $areaTabs[] = [
                'key' => $key,
                'label' => $meta['label'],
                'active' => $key === $areaParam,
            ];
        }

        $html = $this->twig->render('Observation/Seismic/seismic.index.html.twig', [
            'area' => $areaParam,
            'area_label' => self::AREAS[$areaParam]['label'],
            'area_tabs' => $areaTabs,
            'events' => $events,
            'events_count' => count($events),
            'unknown_count' => $unknownCount,
            'features_json' => json_encode($features, JSON_THROW_ON_ERROR | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT),
            'legend' => $legend,
            'last_activity' => $lastActivity,
            'update_date' => $updateDate,
            'error' => $error,
        ]);

        return new Response($html);
    }
}
