<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Service\WeatherDictionary;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class WeatherDictionaryTest extends TestCase
{
    public function testKnownWeatherTypeReturnsLabelKeyAndIcon(): void
    {
        self::assertSame('weather.type.1', WeatherDictionary::weatherTypeLabel(1));
        self::assertSame('bi-sun', WeatherDictionary::weatherTypeIcon(1));
    }

    public function testUnknownWeatherTypeFallsBack(): void
    {
        self::assertSame('weather.type.unknown', WeatherDictionary::weatherTypeLabel(999));
        self::assertSame('bi-question-circle', WeatherDictionary::weatherTypeIcon(999));
    }

    public function testWindSpeedLabels(): void
    {
        self::assertSame('weather.wind_speed.1', WeatherDictionary::windSpeedLabel(1));
        self::assertSame('weather.wind_speed.4', WeatherDictionary::windSpeedLabel(4));
        self::assertSame('weather.wind_speed.unknown', WeatherDictionary::windSpeedLabel(9));
    }

    public function testPrecipitationLabelHandlesNull(): void
    {
        self::assertNull(WeatherDictionary::precipitationLabel(null));
        self::assertSame('weather.precipitation.1', WeatherDictionary::precipitationLabel(1));
        self::assertSame('weather.precipitation.3', WeatherDictionary::precipitationLabel(3));
    }

    #[DataProvider('windDirectionCases')]
    public function testWindDirectionLabel(string $code, string $expected): void
    {
        self::assertSame($expected, WeatherDictionary::windDirectionLabel($code));
    }

    /**
     * @return iterable<string, array{0:string,1:string}>
     */
    public static function windDirectionCases(): iterable
    {
        yield 'north'      => ['N', 'wind.direction.n'];
        yield 'north-east' => ['NE', 'wind.direction.ne'];
        yield 'lowercase'  => ['sw', 'wind.direction.sw'];
        yield 'empty'      => ['', 'wind.direction.variable'];
        yield 'unknown'    => ['X', 'X'];
    }
}
