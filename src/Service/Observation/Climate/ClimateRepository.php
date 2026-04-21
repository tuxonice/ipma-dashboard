<?php

declare(strict_types=1);

namespace App\Service\Observation\Climate;

use App\Service\MunicipalitySlug;
use Psr\SimpleCache\CacheInterface;
use Tlab\IpmaApi\Dto\Climate\ClimateObservation;
use Tlab\IpmaApi\Dto\Service\DistrictLocation;
use Tlab\IpmaApi\IpmaObservation;

/**
 * Repository over IPMA's per-municipality climate CSV endpoints.
 *
 * Wires up five metrics: maximum daily temperature, minimum daily
 * temperature, total daily precipitation, daily reference
 * evapotranspiration (ET0), and the monthly Palmer Drought Severity
 * Index (PDSI).
 *
 * Only mainland Portugal is supported by the dataset; the archipelagos
 * return 404 for these paths.
 */
final class ClimateRepository
{
    public function __construct(private readonly CacheInterface $cache)
    {
    }

    /**
     * Maximum daily temperature series for the municipality pointed to by a
     * forecast location (uses its `idDistrict` + `idMunicipality`).
     *
     * @return list<ClimateObservation>
     */
    public function maxTemperatureFor(DistrictLocation $location): array
    {
        if (!MunicipalitySlug::hasClimateData($location->idDistrict)) {
            return [];
        }

        $districtSlug = (string) MunicipalitySlug::districtSlug($location->idDistrict);
        $muniSlug = MunicipalitySlug::municipalitySlug($location);
        $dico = MunicipalitySlug::dico($location->idDistrict, $location->idMunicipality);

        return IpmaObservation::createMaximumDailyTemperatureApi($this->cache)
            ->from($districtSlug, $muniSlug, $dico)
            ->get();
    }

    /**
     * Minimum daily temperature series for the municipality pointed to by a
     * forecast location.
     *
     * @return list<ClimateObservation>
     */
    public function minTemperatureFor(DistrictLocation $location): array
    {
        if (!MunicipalitySlug::hasClimateData($location->idDistrict)) {
            return [];
        }

        $districtSlug = (string) MunicipalitySlug::districtSlug($location->idDistrict);
        $muniSlug = MunicipalitySlug::municipalitySlug($location);
        $dico = MunicipalitySlug::dico($location->idDistrict, $location->idMunicipality);

        return IpmaObservation::createMinimumDailyTemperatureApi($this->cache)
            ->from($districtSlug, $muniSlug, $dico)
            ->get();
    }

    /**
     * Total daily precipitation series for the municipality pointed to by a
     * forecast location.
     *
     * @return list<ClimateObservation>
     */
    public function precipitationFor(DistrictLocation $location): array
    {
        if (!MunicipalitySlug::hasClimateData($location->idDistrict)) {
            return [];
        }

        $districtSlug = (string) MunicipalitySlug::districtSlug($location->idDistrict);
        $muniSlug = MunicipalitySlug::municipalitySlug($location);
        $dico = MunicipalitySlug::dico($location->idDistrict, $location->idMunicipality);

        return IpmaObservation::createTotalDailyPrecipitationApi($this->cache)
            ->from($districtSlug, $muniSlug, $dico)
            ->get();
    }

    /**
     * Daily reference evapotranspiration (ET0) series for the municipality
     * pointed to by a forecast location.
     *
     * @return list<ClimateObservation>
     */
    public function evapotranspirationFor(DistrictLocation $location): array
    {
        if (!MunicipalitySlug::hasClimateData($location->idDistrict)) {
            return [];
        }

        $districtSlug = (string) MunicipalitySlug::districtSlug($location->idDistrict);
        $muniSlug = MunicipalitySlug::municipalitySlug($location);
        $dico = MunicipalitySlug::dico($location->idDistrict, $location->idMunicipality);

        return IpmaObservation::createDailyEvapotranspirationReferenceApi($this->cache)
            ->from($districtSlug, $muniSlug, $dico)
            ->get();
    }

    /**
     * Monthly Palmer Drought Severity Index (PDSI) series for the
     * municipality pointed to by a forecast location.
     *
     * @return list<ClimateObservation>
     */
    public function pdsiFor(DistrictLocation $location): array
    {
        if (!MunicipalitySlug::hasClimateData($location->idDistrict)) {
            return [];
        }

        $districtSlug = (string) MunicipalitySlug::districtSlug($location->idDistrict);
        $muniSlug = MunicipalitySlug::municipalitySlug($location);
        $dico = MunicipalitySlug::dico($location->idDistrict, $location->idMunicipality);

        return IpmaObservation::createPalmerDroughtSeverityIndexApi($this->cache)
            ->from($districtSlug, $muniSlug, $dico)
            ->get();
    }
}
