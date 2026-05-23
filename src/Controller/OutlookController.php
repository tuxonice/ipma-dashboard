<?php

declare(strict_types=1);

namespace App\Controller;

use App\Framework\RouteLoader;
use App\Service\Forecast\Meteorology\UvIndexLevel;
use App\Service\Forecast\Meteorology\UvIndexRepository;
use App\Service\OutlookRepository;
use App\Service\Services\LocationRepository;
use App\Service\TemperatureBand;
use App\Service\WeatherDictionary;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\Translation\TranslatorInterface;
use Tlab\IpmaApi\Dto\Forecast\DailyForecastByDayRecord;
use Tlab\IpmaApi\Dto\Forecast\UltravioletRiskRecord;
use Tlab\IpmaApi\Dto\Service\DistrictLocation;
use Tlab\IpmaApi\Enums\ForecastDayEnum;
use Tlab\IpmaApi\Exception\IpmaApiException;
use Twig\Environment;

/**
 * Portugal-wide forecast outlook.
 *
 * Renders the IPMA "daily forecast aggregated by day" feed for one of
 * the three available days (today, tomorrow, day after tomorrow): a
 * Leaflet map of every IPMA location coloured by Tmax, plus a table
 * grouped by region with weather type, temperatures, rainfall
 * probability and wind.
 */
final class OutlookController
{
    private const DAYS = [
        'today'     => ['enum' => ForecastDayEnum::TODAY,              'label' => 'outlook.day.today',     'offset' => 0, 'segment' => null],
        'tomorrow'  => ['enum' => ForecastDayEnum::TOMORROW,           'label' => 'outlook.day.tomorrow',  'offset' => 1, 'segment' => 'day_tomorrow'],
        'day-after' => ['enum' => ForecastDayEnum::DAY_AFTER_TOMORROW, 'label' => 'outlook.day.day_after', 'offset' => 2, 'segment' => 'day_day_after'],
    ];

    public function __construct(
        private readonly Environment $twig,
        private readonly OutlookRepository $outlook,
        private readonly LocationRepository $locations,
        private readonly TranslatorInterface $translator,
        private readonly UvIndexRepository $uvIndex,
    ) {
    }

    public function index(Request $request): Response
    {
        $locale = $request->getLocale();
        $urlDay = strtolower((string) $request->attributes->get('day', 'today'));

        $dayKey = 'today';
        foreach (self::DAYS as $key => $meta) {
            if ($meta['segment'] !== null && RouteLoader::segment($meta['segment'], $locale) === $urlDay) {
                $dayKey = $key;
                break;
            }
        }
        $dayMeta = self::DAYS[$dayKey];

        $error = null;
        $regions = [];
        $features = [];
        $updateAt = null;
        $forecastDate = null;
        $totalLocations = 0;

        try {
            $result = $this->outlook->forDay($dayMeta['enum']);
            $records = $result['records'];
            $updateAt = $result['update_at'];

            // The feed itself doesn't include the forecast date, but it's
            // always today + offset (in the IPMA timezone, which matches
            // the server for our purposes — Portugal/UTC).
            $forecastDate = (new \DateTimeImmutable('today'))->modify(sprintf('+%d days', $dayMeta['offset']));

            // Build a globalIdLocal -> location lookup for region grouping.
            $locationsById = [];
            foreach ($this->locations->all() as $location) {
                $locationsById[$location->globalIdLocal] = $location;
            }

            // Fetch UV index data grouped by location for the forecast date.
            $uvByLocation = $this->uvIndex->groupedByLocation();
            $forecastDateStr = $forecastDate->format('Y-m-d');

            /** @var array<int, list<array<string,mixed>>> $byRegion */
            $byRegion = [];
            foreach ($records as $record) {
                $location = $locationsById[$record->globalIdLocal] ?? null;
                $regionId = $location->idRegion ?? 0;

                $row = $this->buildRow($record, $location, $uvByLocation[$record->globalIdLocal] ?? [], $forecastDateStr);
                $byRegion[$regionId][] = $row;
                $features[] = [
                    'lat' => $record->latitude,
                    'lng' => $record->longitude,
                    'name' => $row['name'],
                    'icon' => $row['weather_icon'],
                    'weather' => $this->translator->trans($row['weather_label']),
                    't_min' => $record->minTemp,
                    't_max' => $record->maxTemp,
                    'rain_prob' => $record->rainfallProb,
                    'wind' => $this->translator->trans($row['wind_dir_label']) . ' ' . $this->translator->trans($row['wind_speed_label']),
                    'color' => $row['color'],
                ];
                $totalLocations++;
            }

            ksort($byRegion);
            foreach ($byRegion as $regionId => $rows) {
                usort($rows, static fn(array $a, array $b) => strcmp($a['name'], $b['name']));
                $tz = new \DateTimeZone(LocationRepository::regionTimezone($regionId));
                $regions[] = [
                    'id' => $regionId,
                    'label' => LocationRepository::regionLabel($regionId ?: 1),
                    'rows' => $rows,
                    'timezone' => $tz->getName(),
                    'update_at_local' => $updateAt?->setTimezone($tz),
                ];
            }
        } catch (IpmaApiException $e) {
            $error = $e->getMessage();
        } catch (\Throwable $e) {
            $error = $e->getMessage();
        }

        $dayTabs = [];
        foreach (self::DAYS as $key => $meta) {
            $dayTabs[] = [
                'key' => $key,
                'label' => $meta['label'],
                'active' => $key === $dayKey,
            ];
        }

        return new Response($this->twig->render('outlook/index.html.twig', [
            'day' => $dayKey,
            'day_label' => $dayMeta['label'],
            'day_tabs' => $dayTabs,
            'forecast_date' => $forecastDate,
            'update_at' => $updateAt,
            'regions' => $regions,
            'total_locations' => $totalLocations,
            'features_json' => json_encode($features, JSON_THROW_ON_ERROR | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT),
            'temperature_legend' => TemperatureBand::bands(),
            'error' => $error,
            // Override matched URL slug with the internal day key so cross-locale
            // links (hreflang, language switcher) can translate it via LocalizedRoutingExtension.
            'app_route_params' => ['day' => $dayKey],
        ]));
    }

    /**
     * @param list<UltravioletRiskRecord> $uvRecords
     * @return array<string, mixed>
     */
    private function buildRow(DailyForecastByDayRecord $record, ?DistrictLocation $location, array $uvRecords, ?string $forecastDate): array
    {
        $band = TemperatureBand::classify($record->maxTemp);

        // Find UV index for the matching forecast date.
        $uvIndex = null;
        foreach ($uvRecords as $uv) {
            if ($uv->forecastDate === $forecastDate) {
                $uvIndex = $uv->uvIndex;
                break;
            }
        }
        $uvLevel = $uvIndex !== null ? UvIndexLevel::fromUvIndex($uvIndex) : null;

        return [
            'global_id_local' => $record->globalIdLocal,
            'name' => $location->name ?? sprintf('Location %d', $record->globalIdLocal),
            'weather_label' => WeatherDictionary::weatherTypeLabel($record->idWeatherType),
            'weather_icon' => WeatherDictionary::weatherTypeIcon($record->idWeatherType),
            'wind_dir_label' => WeatherDictionary::windDirectionLabel($record->winDir),
            'wind_speed_label' => WeatherDictionary::windSpeedLabel($record->windSpeedClass),
            't_min' => $record->minTemp,
            't_max' => $record->maxTemp,
            'rain_prob' => $record->rainfallProb,
            'rain_intensity' => WeatherDictionary::precipitationLabel($record->rainfallIntensity),
            'color' => $band['color'],
            'text_color' => $band['text'],
            'temp_label' => $band['label'],
            'uv_index' => $uvIndex,
            'uv_level' => $uvLevel,
            'uv_label' => $uvLevel !== null ? UvIndexLevel::label($uvLevel) : null,
            'uv_color' => $uvLevel !== null ? UvIndexLevel::color($uvLevel) : null,
            'uv_text_color' => $uvLevel !== null ? UvIndexLevel::textColor($uvLevel) : null,
        ];
    }
}
