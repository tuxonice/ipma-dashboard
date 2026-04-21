<?php

declare(strict_types=1);

namespace App\Framework;

use App\Controller\Forecast\Meteorology\FireRiskController;
use App\Controller\Forecast\Meteorology\UvController;
use App\Controller\Forecast\Oceanography\SeaController;
use App\Controller\Forecast\Warnings\WarningController;
use App\Controller\HomeController;
use App\Controller\Observation\Biology\BivalveController;
use App\Controller\Observation\Climate\ClimateController;
use App\Controller\Observation\Meteorology\StationController;
use App\Controller\Observation\Seismic\SeismicController;
use App\Controller\OutlookController;
use App\Controller\TermsController;
use App\Controller\Services\LocationController;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/**
 * Central place to declare the application's routes.
 *
 * Kept as a plain PHP collection (rather than attributes or YAML) to
 * keep the dependency surface minimal during bootstrap.
 */
final class RouteLoader
{
    public static function load(): RouteCollection
    {
        $routes = new RouteCollection();

        $routes->add('home', new Route(
            path: '/',
            defaults: ['_controller' => [HomeController::class, 'index']],
            methods: ['GET'],
        ));

        $routes->add('locations_index', new Route(
            path: '/locations',
            defaults: ['_controller' => [LocationController::class, 'index']],
            methods: ['GET'],
        ));

        $routes->add('locations_show', new Route(
            path: '/locations/{globalIdLocal}',
            defaults: ['_controller' => [LocationController::class, 'show']],
            requirements: ['globalIdLocal' => '\d+'],
            methods: ['GET'],
        ));

        $routes->add('warnings_index', new Route(
            path: '/warnings',
            defaults: ['_controller' => [WarningController::class, 'index']],
            methods: ['GET'],
        ));

        $routes->add('sea_index', new Route(
            path: '/sea',
            defaults: ['_controller' => [SeaController::class, 'index']],
            methods: ['GET'],
        ));

        $routes->add('sea_show', new Route(
            path: '/sea/{globalIdLocal}',
            defaults: ['_controller' => [SeaController::class, 'show']],
            requirements: ['globalIdLocal' => '\d+'],
            methods: ['GET'],
        ));

        $routes->add('stations_index', new Route(
            path: '/stations',
            defaults: ['_controller' => [StationController::class, 'index']],
            methods: ['GET'],
        ));

        $routes->add('stations_map', new Route(
            path: '/stations/map',
            defaults: ['_controller' => [StationController::class, 'map']],
            methods: ['GET'],
        ));

        $routes->add('stations_show', new Route(
            path: '/stations/{id}',
            defaults: ['_controller' => [StationController::class, 'show']],
            requirements: ['id' => '\d+'],
            methods: ['GET'],
        ));

        $routes->add('fire_risk_index', new Route(
            path: '/fire-risk',
            defaults: ['_controller' => [FireRiskController::class, 'index']],
            methods: ['GET'],
        ));

        $routes->add('uv_index', new Route(
            path: '/uv',
            defaults: ['_controller' => [UvController::class, 'index']],
            methods: ['GET'],
        ));

        $routes->add('seismic_index', new Route(
            path: '/seismic',
            defaults: ['_controller' => [SeismicController::class, 'index']],
            methods: ['GET'],
        ));

        $routes->add('outlook_index', new Route(
            path: '/outlook',
            defaults: ['_controller' => [OutlookController::class, 'index']],
            methods: ['GET'],
        ));

        $routes->add('bivalves_index', new Route(
            path: '/bivalves',
            defaults: ['_controller' => [BivalveController::class, 'index']],
            methods: ['GET'],
        ));

        $routes->add('climate_index', new Route(
            path: '/climate',
            defaults: ['_controller' => [ClimateController::class, 'index']],
            methods: ['GET'],
        ));

        $routes->add('climate_max_temperature', new Route(
            path: '/climate/max-temperature/{globalIdLocal}',
            defaults: ['_controller' => [ClimateController::class, 'maxTemperature']],
            requirements: ['globalIdLocal' => '\d+'],
            methods: ['GET'],
        ));

        $routes->add('climate_min_temperature', new Route(
            path: '/climate/min-temperature/{globalIdLocal}',
            defaults: ['_controller' => [ClimateController::class, 'minTemperature']],
            requirements: ['globalIdLocal' => '\d+'],
            methods: ['GET'],
        ));

        $routes->add('climate_precipitation', new Route(
            path: '/climate/precipitation/{globalIdLocal}',
            defaults: ['_controller' => [ClimateController::class, 'precipitation']],
            requirements: ['globalIdLocal' => '\d+'],
            methods: ['GET'],
        ));

        $routes->add('climate_evapotranspiration', new Route(
            path: '/climate/evapotranspiration/{globalIdLocal}',
            defaults: ['_controller' => [ClimateController::class, 'evapotranspiration']],
            requirements: ['globalIdLocal' => '\d+'],
            methods: ['GET'],
        ));

        $routes->add('climate_pdsi', new Route(
            path: '/climate/pdsi/{globalIdLocal}',
            defaults: ['_controller' => [ClimateController::class, 'pdsi']],
            requirements: ['globalIdLocal' => '\d+'],
            methods: ['GET'],
        ));

        $routes->add('terms', new Route(
            path: '/terms',
            defaults: ['_controller' => [TermsController::class, 'index']],
            methods: ['GET'],
        ));

        return $routes;
    }
}
