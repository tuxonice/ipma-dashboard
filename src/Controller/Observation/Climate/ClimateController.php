<?php

declare(strict_types=1);

namespace App\Controller\Observation\Climate;

use App\Service\MunicipalitySlug;
use App\Service\Observation\Climate\ClimateRepository;
use App\Service\Observation\Climate\PdsiLevel;
use App\Service\Services\LocationRepository;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tlab\IpmaApi\Dto\Climate\ClimateObservation;
use Tlab\IpmaApi\Dto\Service\DistrictLocation;
use Tlab\IpmaApi\Exception\IpmaApiException;
use Twig\Environment;

/**
 * Climate observations (per-municipality CSV series published by IPMA).
 *
 * Slices currently exposed: "Maximum daily temperature" and
 * "Minimum daily temperature". The upstream dataset only covers mainland
 * Portugal, so the picker filters out islands.
 */
final class ClimateController
{
    public function __construct(
        private readonly Environment $twig,
        private readonly LocationRepository $locations,
        private readonly ClimateRepository $climate,
    ) {
    }

    public function index(): Response
    {
        $locations = array_values(array_filter(
            $this->locations->all(),
            static fn(DistrictLocation $l) => MunicipalitySlug::hasClimateData($l->idDistrict),
        ));

        usort(
            $locations,
            static fn(DistrictLocation $a, DistrictLocation $b) => strcmp(
                (string) MunicipalitySlug::districtLabel($a->idDistrict) . $a->name,
                (string) MunicipalitySlug::districtLabel($b->idDistrict) . $b->name,
            ),
        );

        $grouped = [];
        foreach ($locations as $location) {
            $grouped[$location->idDistrict][] = $location;
        }

        $districts = [];
        foreach ($grouped as $idDistrict => $items) {
            $districts[] = [
                'id' => $idDistrict,
                'label' => MunicipalitySlug::districtLabel($idDistrict) ?? sprintf('District %d', $idDistrict),
                'locations' => $items,
            ];
        }

        return new Response($this->twig->render('Observation/Climate/climate.index.html.twig', [
            'districts' => $districts,
        ]));
    }

    public function maxTemperature(int $globalIdLocal): Response
    {
        return $this->renderClimateSeries(
            $globalIdLocal,
            'maximum',
            'Observation/Climate/climate.max-temperature.html.twig',
            fn(DistrictLocation $location) => $this->climate->maxTemperatureFor($location),
        );
    }

    public function minTemperature(int $globalIdLocal): Response
    {
        return $this->renderClimateSeries(
            $globalIdLocal,
            'minimum',
            'Observation/Climate/climate.min-temperature.html.twig',
            fn(DistrictLocation $location) => $this->climate->minTemperatureFor($location),
        );
    }

    public function precipitation(int $globalIdLocal): Response
    {
        return $this->renderClimateSeries(
            $globalIdLocal,
            'mean',
            'Observation/Climate/climate.precipitation.html.twig',
            fn(DistrictLocation $location) => $this->climate->precipitationFor($location),
        );
    }

    public function evapotranspiration(int $globalIdLocal): Response
    {
        return $this->renderClimateSeries(
            $globalIdLocal,
            'mean',
            'Observation/Climate/climate.evapotranspiration.html.twig',
            fn(DistrictLocation $location) => $this->climate->evapotranspirationFor($location),
        );
    }

    public function pdsi(int $globalIdLocal): Response
    {
        $location = $this->locations->findByGlobalId($globalIdLocal);
        if ($location === null) {
            throw new NotFoundHttpException(sprintf('Location %d not found.', $globalIdLocal));
        }

        if (!MunicipalitySlug::hasClimateData($location->idDistrict)) {
            throw new NotFoundHttpException(sprintf(
                'Climate data is not available for %s (outside mainland Portugal).',
                $location->name,
            ));
        }

        $records = [];
        $rows = [];
        $counts = array_fill_keys(PdsiLevel::all(), 0);
        $error = null;

        try {
            $records = $this->climate->pdsiFor($location);
        } catch (IpmaApiException $e) {
            $error = $e->getMessage();
        } catch (\Throwable $e) {
            $error = $e->getMessage();
        }

        foreach ($records as $record) {
            $value = is_numeric($record->mean) ? (float) $record->mean : null;
            if ($value === null) {
                $rows[] = ['record' => $record, 'value' => null, 'level' => null, 'level_label' => '', 'color' => '#adb5bd', 'text' => '#212529'];
                continue;
            }

            $level = PdsiLevel::fromValue($value);
            $counts[$level]++;
            $rows[] = [
                'record' => $record,
                'value' => $value,
                'level' => $level,
                'level_label' => PdsiLevel::label($level),
                'color' => PdsiLevel::color($level),
                'text' => PdsiLevel::textColor($level),
            ];
        }

        $legend = [];
        foreach (PdsiLevel::all() as $level) {
            $legend[] = [
                'level' => $level,
                'label' => PdsiLevel::label($level),
                'color' => PdsiLevel::color($level),
                'text' => PdsiLevel::textColor($level),
                'count' => $counts[$level],
            ];
        }

        return new Response($this->twig->render('Observation/Climate/climate.pdsi.html.twig', [
            'location' => $location,
            'district_label' => MunicipalitySlug::districtLabel($location->idDistrict),
            'dico' => MunicipalitySlug::dico($location->idDistrict, $location->idMunicipality),
            'records' => $records,
            'rows' => $rows,
            'chart' => $this->buildChart($records, 'mean'),
            'legend' => $legend,
            'error' => $error,
        ]));
    }

    /**
     * Shared rendering pipeline for the per-municipality climate CSV views
     * (max temperature, min temperature, precipitation, …).
     *
     * @param 'minimum'|'maximum'|'mean'|'std' $field
     * @param callable(DistrictLocation): list<ClimateObservation> $fetch
     */
    private function renderClimateSeries(
        int $globalIdLocal,
        string $field,
        string $template,
        callable $fetch,
    ): Response {
        $location = $this->locations->findByGlobalId($globalIdLocal);
        if ($location === null) {
            throw new NotFoundHttpException(sprintf('Location %d not found.', $globalIdLocal));
        }

        if (!MunicipalitySlug::hasClimateData($location->idDistrict)) {
            throw new NotFoundHttpException(sprintf(
                'Climate data is not available for %s (outside mainland Portugal).',
                $location->name,
            ));
        }

        $records = [];
        $error = null;

        try {
            /** @var list<ClimateObservation> $records */
            $records = $fetch($location);
        } catch (IpmaApiException $e) {
            $error = $e->getMessage();
        } catch (\Throwable $e) {
            $error = $e->getMessage();
        }

        return new Response($this->twig->render($template, [
            'location' => $location,
            'district_label' => MunicipalitySlug::districtLabel($location->idDistrict),
            'dico' => MunicipalitySlug::dico($location->idDistrict, $location->idMunicipality),
            'records' => $records,
            'chart' => $this->buildChart($records, $field),
            'error' => $error,
        ]));
    }

    /**
     * Pre-compute a lightweight summary the template uses to draw an inline
     * SVG chart (no JS dependency for a simple series).
     *
     * @param list<ClimateObservation>            $records
     * @param 'minimum'|'maximum'|'mean'|'std'    $field
     *
     * @return array{
     *     points: list<array{date: string, value: float}>,
     *     min: ?float,
     *     max: ?float,
     *     avg: ?float,
     *     latest: ?ClimateObservation,
     * }
     */
    private function buildChart(array $records, string $field = 'maximum'): array
    {
        $points = [];
        $values = [];

        foreach ($records as $record) {
            $raw = $record->{$field};
            if ($raw === '' || !is_numeric($raw)) {
                continue;
            }
            $value = (float) $raw;
            $values[] = $value;
            $points[] = ['date' => $record->date, 'value' => $value];
        }

        return [
            'points' => $points,
            'min' => $values === [] ? null : min($values),
            'max' => $values === [] ? null : max($values),
            'avg' => $values === [] ? null : array_sum($values) / count($values),
            'latest' => $records === [] ? null : $records[array_key_last($records)],
        ];
    }
}
