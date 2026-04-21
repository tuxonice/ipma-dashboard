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
use App\Service\Forecast\Meteorology\FireRiskRepository;
use App\Service\Forecast\Meteorology\UvIndexRepository;
use App\Service\Forecast\Oceanography\SeaForecastRepository;
use App\Service\Forecast\Warnings\WarningRepository;
use App\Service\ForecastRepository;
use App\Service\IpmaConnectorFactory;
use App\Service\Observation\Biology\BivalveRepository;
use App\Service\Observation\Climate\ClimateRepository;
use App\Service\Observation\Meteorology\StationHourlyRepository;
use App\Service\Observation\Meteorology\StationObservationRepository;
use App\Service\Observation\Meteorology\StationRepository;
use App\Service\Observation\Seismic\SeismicRepository;
use App\Service\OutlookRepository;
use App\Service\Services\LocationRepository;
use App\Service\Services\SeaLocationRepository;
use Psr\Container\ContainerInterface;
use Psr\SimpleCache\CacheInterface;
use Symfony\Bridge\Twig\Extension\RoutingExtension;
use Symfony\Bridge\Twig\Extension\TranslationExtension;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Routing\Generator\UrlGenerator;
use Symfony\Component\Routing\RequestContext;
use Symfony\Contracts\Translation\LocaleAwareInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Tlab\IpmaApi\ApiConnectorInterface;
use Twig\Environment;
use Twig\Extra\Intl\IntlExtension;
use Twig\Loader\FilesystemLoader;

/**
 * Builds the DI container used by the kernel.
 *
 * Keeps service wiring in one place so controllers stay thin and
 * framework code stays easy to reason about.
 */
final class ContainerFactory
{
    public static function create(string $projectDir, string $environment, bool $debug): ContainerInterface
    {
        $container = new ContainerBuilder();

        $container->setParameter('app.project_dir', $projectDir);
        $container->setParameter('app.environment', $environment);
        $container->setParameter('app.debug', $debug);

        // --- Twig -----------------------------------------------------------
        $container->register('twig.loader', FilesystemLoader::class)
            ->addArgument([$projectDir . '/templates']);

        $container->register('twig', Environment::class)
            ->setPublic(true)
            ->addArgument(new Reference('twig.loader'))
            ->addArgument([
                'cache' => $debug ? false : $projectDir . '/var/cache/twig',
                'debug' => $debug,
                'strict_variables' => $debug,
                'auto_reload' => true,
            ]);

        // --- Routing (for URL generation inside templates) ------------------
        $container->register('router.request_context', RequestContext::class);
        $container->register('router.url_generator', UrlGenerator::class)
            ->setFactory([UrlGeneratorFactory::class, 'create'])
            ->addArgument(new Reference('router.request_context'));

        // Register Twig routing extension so templates can use path()/url().
        $container->register('twig.extension.routing', RoutingExtension::class)
            ->addArgument(new Reference('router.url_generator'));
        $container->getDefinition('twig')
            ->addMethodCall('addExtension', [new Reference('twig.extension.routing')]);

        // --- Translation (PT default, EN fallback) -------------------------
        $container->register('translator', \Symfony\Component\Translation\Translator::class)
            ->setPublic(true)
            ->setFactory([TranslatorFactory::class, 'create'])
            ->setArguments([$projectDir . '/translations', 'pt', ['pt', 'en']]);

        $container->setAlias(TranslatorInterface::class, 'translator')->setPublic(true);
        $container->setAlias(LocaleAwareInterface::class, 'translator')->setPublic(true);

        $container->register('twig.extension.translation', TranslationExtension::class)
            ->addArgument(new Reference('translator'));
        $container->getDefinition('twig')
            ->addMethodCall('addExtension', [new Reference('twig.extension.translation')]);

        $container->register('twig.extension.intl', IntlExtension::class);
        $container->getDefinition('twig')
            ->addMethodCall('addExtension', [new Reference('twig.extension.intl')]);

        // Expose the debug flag to templates so error pages can decide whether
        // to render technical detail (stack traces, upstream messages) or a
        // generic message in production.
        $container->getDefinition('twig')
            ->addMethodCall('addGlobal', ['app_debug', $debug]);

        // --- IPMA client (cached PSR-16 filesystem connector) ---------------
        // The library's `Ipma*::create*Api()` factories now accept a PSR-16
        // cache directly; only repositories that bypass those factories
        // (e.g. `StationObservationRepository` calling `fetchData()` on a
        // not-yet-wrapped endpoint) still need the full connector.
        $container->register(CacheInterface::class, CacheInterface::class)
            ->setFactory([IpmaConnectorFactory::class, 'createCache'])
            ->addArgument($projectDir . '/var/cache/ipma')
            ->addArgument(1800)
            ->addArgument($projectDir . '/var/log/ipma-cache.log');

        $container->register(ApiConnectorInterface::class, ApiConnectorInterface::class)
            ->setFactory([IpmaConnectorFactory::class, 'create'])
            ->addArgument($projectDir . '/var/cache/ipma')
            ->addArgument(1800)
            ->addArgument($projectDir . '/var/log/ipma-cache.log');

        $container->register(LocationRepository::class, LocationRepository::class)
            ->addArgument(new Reference(CacheInterface::class));

        $container->register(ForecastRepository::class, ForecastRepository::class)
            ->addArgument(new Reference(CacheInterface::class));

        $container->register(WarningRepository::class, WarningRepository::class)
            ->addArgument(new Reference(CacheInterface::class));

        $container->register(SeaLocationRepository::class, SeaLocationRepository::class)
            ->addArgument(new Reference(CacheInterface::class));

        $container->register(SeaForecastRepository::class, SeaForecastRepository::class)
            ->addArgument(new Reference(CacheInterface::class));

        $container->register(StationRepository::class, StationRepository::class)
            ->addArgument(new Reference(CacheInterface::class));

        $container->register(StationObservationRepository::class, StationObservationRepository::class)
            ->addArgument(new Reference(ApiConnectorInterface::class));

        $container->register(StationHourlyRepository::class, StationHourlyRepository::class)
            ->addArgument(new Reference(ApiConnectorInterface::class));

        $container->register(FireRiskRepository::class, FireRiskRepository::class)
            ->addArgument(new Reference(CacheInterface::class));

        $container->register(ClimateRepository::class, ClimateRepository::class)
            ->addArgument(new Reference(CacheInterface::class));

        $container->register(UvIndexRepository::class, UvIndexRepository::class)
            ->addArgument(new Reference(CacheInterface::class));

        $container->register(SeismicRepository::class, SeismicRepository::class)
            ->addArgument(new Reference(CacheInterface::class));

        $container->register(OutlookRepository::class, OutlookRepository::class)
            ->addArgument(new Reference(ApiConnectorInterface::class));

        $container->register(BivalveRepository::class, BivalveRepository::class)
            ->addArgument(new Reference(CacheInterface::class));

        // --- Controllers (public so ControllerResolver can fetch them) ------
        $container->register(HomeController::class, HomeController::class)
            ->setPublic(true)
            ->addArgument(new Reference('twig'));

        $container->register(LocationController::class, LocationController::class)
            ->setPublic(true)
            ->addArgument(new Reference('twig'))
            ->addArgument(new Reference(LocationRepository::class))
            ->addArgument(new Reference(ForecastRepository::class))
            ->addArgument(new Reference(WarningRepository::class));

        $container->register(WarningController::class, WarningController::class)
            ->setPublic(true)
            ->addArgument(new Reference('twig'))
            ->addArgument(new Reference(WarningRepository::class))
            ->addArgument(new Reference(LocationRepository::class));

        $container->register(SeaController::class, SeaController::class)
            ->setPublic(true)
            ->addArgument(new Reference('twig'))
            ->addArgument(new Reference(SeaLocationRepository::class))
            ->addArgument(new Reference(SeaForecastRepository::class));

        $container->register(StationController::class, StationController::class)
            ->setPublic(true)
            ->addArgument(new Reference('twig'))
            ->addArgument(new Reference(StationRepository::class))
            ->addArgument(new Reference(StationObservationRepository::class))
            ->addArgument(new Reference(StationHourlyRepository::class));

        $container->register(FireRiskController::class, FireRiskController::class)
            ->setPublic(true)
            ->addArgument(new Reference('twig'))
            ->addArgument(new Reference(FireRiskRepository::class));

        $container->register(ClimateController::class, ClimateController::class)
            ->setPublic(true)
            ->addArgument(new Reference('twig'))
            ->addArgument(new Reference(LocationRepository::class))
            ->addArgument(new Reference(ClimateRepository::class));

        $container->register(UvController::class, UvController::class)
            ->setPublic(true)
            ->addArgument(new Reference('twig'))
            ->addArgument(new Reference(LocationRepository::class))
            ->addArgument(new Reference(UvIndexRepository::class));

        $container->register(SeismicController::class, SeismicController::class)
            ->setPublic(true)
            ->addArgument(new Reference('twig'))
            ->addArgument(new Reference(SeismicRepository::class));

        $container->register(OutlookController::class, OutlookController::class)
            ->setPublic(true)
            ->addArgument(new Reference('twig'))
            ->addArgument(new Reference(OutlookRepository::class))
            ->addArgument(new Reference(LocationRepository::class))
            ->addArgument(new Reference(TranslatorInterface::class))
            ->addArgument(new Reference(UvIndexRepository::class));

        $container->register(BivalveController::class, BivalveController::class)
            ->setPublic(true)
            ->addArgument(new Reference('twig'))
            ->addArgument(new Reference(BivalveRepository::class))
            ->addArgument(new Reference(TranslatorInterface::class));

        $container->register(TermsController::class, TermsController::class)
            ->setPublic(true)
            ->addArgument(new Reference('twig'));

        $container->compile();

        return $container;
    }
}
