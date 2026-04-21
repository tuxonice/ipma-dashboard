<?php

declare(strict_types=1);

namespace App\Controller\Observation\Biology;

use App\Service\Observation\Biology\BivalveRepository;
use App\Service\Observation\Biology\BivalveStatus;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\Translation\TranslatorInterface;
use Tlab\IpmaApi\Exception\IpmaApiException;
use Twig\Environment;

/**
 * Bivalve mollusc harvesting interdictions view.
 *
 * Renders IPMA's `CI_SNMB.geojson` feed as a Leaflet map of production
 * zones (markers coloured by status) plus a table grouped by region
 * showing which species are open vs closed for harvest.
 */
final class BivalveController
{
    public function __construct(
        private readonly Environment $twig,
        private readonly BivalveRepository $bivalves,
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function index(): Response
    {
        $error = null;
        $regions = [];
        $features = [];
        $totalZones = 0;
        $counts = array_fill_keys(BivalveStatus::all(), 0);
        $unknownCount = 0;

        try {
            $result = $this->bivalves->all();

            $byRegion = [];
            foreach ($result['features'] as $feature) {
                $status = BivalveStatus::normalise($feature->status);
                if ($status === BivalveStatus::UNKNOWN) {
                    $unknownCount++;
                } else {
                    $counts[$status]++;
                }

                $interdictions = BivalveRepository::interdictions($feature);

                $row = [
                    'id' => $feature->id,
                    'name' => $feature->name,
                    'code' => $feature->code,
                    'zone_type' => $feature->zoneType,
                    'region_name' => $feature->regionName,
                    'status' => $status,
                    'status_label' => BivalveStatus::label($status),
                    'color' => BivalveStatus::color($status),
                    'text' => BivalveStatus::textColor($status),
                    'bootstrap' => BivalveStatus::bootstrap($status),
                    'lat' => $feature->latitude,
                    'lng' => $feature->longitude,
                    'open' => $interdictions['open'],
                    'close' => $interdictions['close'],
                ];

                $byRegion[$feature->regionName][] = $row;
                $features[] = [
                    'lat' => $feature->latitude,
                    'lng' => $feature->longitude,
                    'name' => $feature->name,
                    'code' => $feature->code,
                    'zone_type' => $feature->zoneType,
                    'region_name' => $feature->regionName,
                    'status' => $status,
                    'status_label' => $this->translator->trans(BivalveStatus::label($status)),
                    'color' => BivalveStatus::color($status),
                    'open' => $interdictions['open'],
                    'close' => $interdictions['close'],
                ];
                $totalZones++;
            }

            ksort($byRegion);
            foreach ($byRegion as $regionName => $rows) {
                usort($rows, static fn(array $a, array $b) => strcmp($a['name'], $b['name']));
                $regions[] = [
                    'label' => $regionName === '' ? 'Unknown region' : $regionName,
                    'rows' => $rows,
                ];
            }
        } catch (IpmaApiException $e) {
            $error = $e->getMessage();
        } catch (\Throwable $e) {
            $error = $e->getMessage();
        }

        $legend = [];
        foreach (BivalveStatus::all() as $status) {
            $legend[] = [
                'status' => $status,
                'label' => BivalveStatus::label($status),
                'color' => BivalveStatus::color($status),
                'count' => $counts[$status],
            ];
        }

        return new Response($this->twig->render('Observation/Biology/bivalves.index.html.twig', [
            'regions' => $regions,
            'total_zones' => $totalZones,
            'unknown_count' => $unknownCount,
            'features_json' => json_encode($features, JSON_THROW_ON_ERROR | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT),
            'legend' => $legend,
            'error' => $error,
        ]));
    }
}
