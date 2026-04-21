<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Service\MunicipalitySlug;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tlab\IpmaApi\Dto\Service\DistrictLocation;

final class MunicipalitySlugTest extends TestCase
{
    public function testDicoIsZeroPaddedDistrictAndMunicipality(): void
    {
        self::assertSame('0705', MunicipalitySlug::dico(7, 5));
        self::assertSame('1106', MunicipalitySlug::dico(11, 6));
        self::assertSame('0101', MunicipalitySlug::dico(1, 1));
    }

    #[DataProvider('slugSamples')]
    public function testSlugifyStripsAccentsAndDashesWhitespace(string $input, string $expected): void
    {
        self::assertSame($expected, MunicipalitySlug::slugify($input));
    }

    /**
     * @return iterable<string, array{0:string,1:string}>
     */
    public static function slugSamples(): iterable
    {
        yield 'plain'          => ['Lisboa', 'lisboa'];
        yield 'accents'        => ['Évora', 'evora'];
        yield 'tilde'          => ['Bragança', 'braganca'];
        yield 'multi word'     => ['Castelo Branco', 'castelo-branco'];
        yield 'trims'          => ['  São João  ', 'sao-joao'];
        yield 'hyphen chain'   => ['Viana do Castelo', 'viana-do-castelo'];
    }

    public function testDistrictLookupsForMainland(): void
    {
        self::assertTrue(MunicipalitySlug::hasClimateData(7));
        self::assertSame('evora', MunicipalitySlug::districtSlug(7));
        self::assertSame('Évora', MunicipalitySlug::districtLabel(7));
    }

    public function testDistrictLookupsRejectIslands(): void
    {
        self::assertFalse(MunicipalitySlug::hasClimateData(31));
        self::assertNull(MunicipalitySlug::districtSlug(31));
        self::assertNull(MunicipalitySlug::districtLabel(31));
    }

    public function testMunicipalitySlugUsesLocationName(): void
    {
        $location = new DistrictLocation(
            globalIdLocal: 1070500,
            name: 'Évora',
            idMunicipality: 5,
            idDistrict: 7,
            idRegion: 1,
            idWarningArea: 'EVR',
            latitude: 38.5667,
            longitude: -7.9,
        );

        self::assertSame('evora', MunicipalitySlug::municipalitySlug($location));
    }
}
