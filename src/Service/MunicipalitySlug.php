<?php

declare(strict_types=1);

namespace App\Service;

use Tlab\IpmaApi\Dto\Service\DistrictLocation;

/**
 * Helpers for composing the URL fragments required by IPMA's per-municipality
 * climate CSV endpoints.
 *
 * IPMA's climate files live under
 * `/observation/climate/<metric>/<district-slug>/<file-slug>-{DICO}-<muni-slug>.csv`.
 * The dataset only covers mainland Portugal (continental districts 1-18); the
 * archipelagos return 404 for these paths.
 */
final class MunicipalitySlug
{
    /**
     * Portuguese continental districts keyed by `DistrictLocation::idDistrict`.
     *
     * Slugs are the lower-cased ASCII-transliterated district names as used in
     * the IPMA climate URL tree.
     */
    private const DISTRICT_SLUGS = [
        1  => ['aveiro', 'Aveiro'],
        2  => ['beja', 'Beja'],
        3  => ['braga', 'Braga'],
        4  => ['braganca', 'Bragança'],
        5  => ['castelo-branco', 'Castelo Branco'],
        6  => ['coimbra', 'Coimbra'],
        7  => ['evora', 'Évora'],
        8  => ['faro', 'Faro'],
        9  => ['guarda', 'Guarda'],
        10 => ['leiria', 'Leiria'],
        11 => ['lisboa', 'Lisboa'],
        12 => ['portalegre', 'Portalegre'],
        13 => ['porto', 'Porto'],
        14 => ['santarem', 'Santarém'],
        15 => ['setubal', 'Setúbal'],
        16 => ['viana-do-castelo', 'Viana do Castelo'],
        17 => ['vila-real', 'Vila Real'],
        18 => ['viseu', 'Viseu'],
    ];

    /**
     * True when climate CSV datasets exist for the given district id.
     */
    public static function hasClimateData(int $idDistrict): bool
    {
        return isset(self::DISTRICT_SLUGS[$idDistrict]);
    }

    public static function districtSlug(int $idDistrict): ?string
    {
        return self::DISTRICT_SLUGS[$idDistrict][0] ?? null;
    }

    public static function districtLabel(int $idDistrict): ?string
    {
        return self::DISTRICT_SLUGS[$idDistrict][1] ?? null;
    }

    /**
     * IPMA's 4-digit DICO code: zero-padded district id followed by the
     * zero-padded municipality id (e.g. 0705 for Évora, 1106 for Lisboa).
     */
    public static function dico(int $idDistrict, int $idMunicipality): string
    {
        return sprintf('%02d%02d', $idDistrict, $idMunicipality);
    }

    /**
     * Lower-cased ASCII slug for a municipality name (accents stripped,
     * whitespace collapsed to dashes), matching IPMA's filename convention.
     */
    public static function slugify(string $name): string
    {
        // Decompose accented characters into base + combining mark (NFD),
        // then drop the combining marks. This avoids libc //TRANSLIT quirks
        // where `ã` becomes `~a` and splits into `-a-`.
        $ascii = $name;
        if (class_exists(\Normalizer::class)) {
            $normalized = \Normalizer::normalize($name, \Normalizer::FORM_D);
            if (is_string($normalized)) {
                $ascii = preg_replace('/\p{Mn}+/u', '', $normalized) ?? $normalized;
            }
        } else {
            $converted = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $name);
            if ($converted !== false && $converted !== '') {
                $ascii = $converted;
            }
        }

        $ascii = preg_replace('/[^A-Za-z0-9]+/', '-', $ascii) ?? '';
        $ascii = trim($ascii, '-');

        return strtolower($ascii);
    }

    public static function municipalitySlug(DistrictLocation $location): string
    {
        return self::slugify($location->name);
    }
}
