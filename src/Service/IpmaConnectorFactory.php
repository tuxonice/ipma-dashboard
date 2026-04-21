<?php

declare(strict_types=1);

namespace App\Service;

use App\Framework\CacheErrorLogger;
use Psr\SimpleCache\CacheInterface;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Cache\Psr16Cache;
use Tlab\IpmaApi\ApiConnector;
use Tlab\IpmaApi\ApiConnectorInterface;

/**
 * Builds a PSR-16 cached IPMA API connector.
 *
 * The IPMA reference data (locations, stations, weather types, wind classes)
 * changes rarely; responses are cached on the local filesystem to keep the
 * dashboard responsive and avoid hammering the upstream API.
 *
 * As of `tuxonice/ipma-api` `dev-ipm-00-require-cache`, the library's
 * `Ipma*::create*Api()` factories accept a {@see CacheInterface} directly
 * (no longer an {@see ApiConnectorInterface}). {@see createCache()} exposes
 * that PSR-16 cache to the DI container; {@see create()} still builds the
 * connector for repositories that bypass those factories and hit the
 * endpoints via `fetchData()` / `fetchCsv()`.
 */
final class IpmaConnectorFactory
{
    public static function createCache(string $cacheDir, int $ttlSeconds = 3600, ?string $logFile = null): CacheInterface
    {
        $adapter = new FilesystemAdapter(
            namespace: 'ipma',
            defaultLifetime: $ttlSeconds,
            directory: $cacheDir,
        );
        $adapter->setLogger(new CacheErrorLogger($logFile ?? $cacheDir . '/../log/ipma-cache.log'));

        return new Psr16Cache($adapter);
    }

    public static function create(string $cacheDir, int $ttlSeconds = 3600, ?string $logFile = null): ApiConnectorInterface
    {
        return new ApiConnector(self::createCache($cacheDir, $ttlSeconds, $logFile), ttlSeconds: $ttlSeconds);
    }
}
