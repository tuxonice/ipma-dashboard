<?php

declare(strict_types=1);

namespace App\Framework;

use Symfony\Component\Routing\Generator\UrlGenerator;
use Symfony\Component\Routing\RequestContext;

/**
 * Factory for a UrlGenerator bound to the application's RouteCollection.
 *
 * Declared as a separate factory so the DI container can inject the
 * RequestContext while we still control route loading in one place.
 */
final class UrlGeneratorFactory
{
    public static function create(RequestContext $context): UrlGenerator
    {
        return new UrlGenerator(RouteLoader::load(), $context);
    }
}
