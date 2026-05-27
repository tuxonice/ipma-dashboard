#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Stamp public/sitemap.xml and public/robots.txt with the site origin
 * configured in APP_URL (.env), preserving every URL path.
 *
 * Run it whenever the domain changes:
 *     php bin/update-seo-urls.php   (or `make seo-urls`)
 *
 * It rewrites only the scheme+host of <loc> entries, xhtml:link hrefs and the
 * robots.txt "Sitemap:" line. The XML namespace URIs (sitemaps.org, w3.org)
 * live in xmlns attributes and are deliberately left alone.
 */

use Symfony\Component\Dotenv\Dotenv;

require dirname(__DIR__) . '/vendor/autoload.php';

$root = dirname(__DIR__);

// Load the environment exactly like public/index.php does.
(new Dotenv())->bootEnv($root . '/.env');

$appUrl = $_SERVER['APP_URL'] ?? $_ENV['APP_URL'] ?? null;
if ($appUrl === null || $appUrl === '') {
    fwrite(STDERR, "APP_URL is not set. Add it to .env, e.g. APP_URL=https://example.com\n");
    exit(1);
}

$parts = parse_url($appUrl);
if (empty($parts['scheme']) || empty($parts['host'])) {
    fwrite(STDERR, "APP_URL is not a valid absolute URL: {$appUrl}\n");
    exit(1);
}

// Normalise to scheme://host[:port] — drop any path or trailing slash.
$origin = $parts['scheme'] . '://' . $parts['host'];
if (!empty($parts['port'])) {
    $origin .= ':' . $parts['port'];
}

/**
 * Read, transform and write a file back only if its contents changed.
 *
 * @param callable(string):string $transform
 */
$rewrite = static function (string $path, callable $transform): bool {
    $original = file_get_contents($path);
    if ($original === false) {
        fwrite(STDERR, "Could not read {$path}\n");
        exit(1);
    }

    $updated = $transform($original);
    if ($updated === $original) {
        return false;
    }

    file_put_contents($path, $updated);

    return true;
};

$publicDir = $root . '/public';

// sitemap.xml — swap the origin of <loc> bodies and href="" attribute values.
$sitemapChanged = $rewrite($publicDir . '/sitemap.xml', static function (string $xml) use ($origin): string {
    $xml = preg_replace('#(<loc>)https?://[^/<\s]+#', '$1' . $origin, $xml);
    $xml = preg_replace('#(href=")https?://[^/"\s]+#', '$1' . $origin, $xml);

    return $xml;
});

// robots.txt — swap the origin of the "Sitemap:" directive.
$robotsChanged = $rewrite($publicDir . '/robots.txt', static function (string $robots) use ($origin): string {
    return preg_replace('#(?m)(^Sitemap:\s*)https?://[^/\s]+#', '$1' . $origin, $robots);
});

printf(
    "Origin: %s\n  public/sitemap.xml: %s\n  public/robots.txt:  %s\n",
    $origin,
    $sitemapChanged ? 'updated' : 'already current',
    $robotsChanged ? 'updated' : 'already current',
);