<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Service\Services\LocationRepository;
use PHPUnit\Framework\TestCase;

final class LocationRepositoryTest extends TestCase
{
    public function testKnownRegionsAreLabelled(): void
    {
        self::assertSame('region.mainland', LocationRepository::regionLabel(1));
        self::assertSame('region.madeira', LocationRepository::regionLabel(2));
        self::assertSame('region.azores', LocationRepository::regionLabel(3));
    }

    public function testUnknownRegionFallsBackToGenericLabel(): void
    {
        self::assertSame('region.unknown', LocationRepository::regionLabel(42));
    }
}
