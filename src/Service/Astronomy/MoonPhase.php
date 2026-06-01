<?php

declare(strict_types=1);

namespace App\Service\Astronomy;

use DateTimeImmutable;

/**
 * Principal moon phase event times (new / first quarter / full / last quarter),
 * computed from Jean Meeus, *Astronomical Algorithms* (2nd ed.), chapter 49.
 *
 * Results are returned in UTC. Meeus's formulae yield Dynamical Time (TD); the
 * ΔT offset (~1 minute) and the small planetary-argument corrections are
 * omitted — both are irrelevant for a date display, which is all this feeds.
 *
 * Pure, dependency-free helper (cf. UvIndexLevel / AwarenessLevel); therefore
 * NOT registered in the DI container.
 */
final class MoonPhase
{
    public const NEW_MOON = 'new';
    public const FIRST_QUARTER = 'first_quarter';
    public const FULL_MOON = 'full';
    public const LAST_QUARTER = 'last_quarter';

    /** @var array<string, array{key: string, emoji: string}> */
    private const META = [
        self::NEW_MOON      => ['key' => 'location.moon_phase_new',           'emoji' => '🌑'],
        self::FIRST_QUARTER => ['key' => 'location.moon_phase_first_quarter', 'emoji' => '🌓'],
        self::FULL_MOON     => ['key' => 'location.moon_phase_full',          'emoji' => '🌕'],
        self::LAST_QUARTER  => ['key' => 'location.moon_phase_last_quarter',  'emoji' => '🌗'],
    ];

    /** @var array<int, string> Quarter index (0..3) → phase type. */
    private const PHASE_BY_INDEX = [
        0 => self::NEW_MOON,
        1 => self::FIRST_QUARTER,
        2 => self::FULL_MOON,
        3 => self::LAST_QUARTER,
    ];

    /**
     * The next $count principal phases strictly after $from, chronologically.
     *
     * @return list<array{type: string, instant: DateTimeImmutable}>
     */
    public static function nextPrincipalPhases(DateTimeImmutable $from, int $count = 4): array
    {
        $jdFrom = $from->getTimestamp() / 86400.0 + 2440587.5;
        // Mean lunation index at $from (new-moon epoch), floored to the nearest
        // quarter step so an event sitting just after $from is not skipped.
        $kApprox = ($jdFrom - 2451550.09766) / 29.530588861;
        $k = floor($kApprox * 4.0) / 4.0;

        $out = [];
        while (count($out) < $count) {
            $instant = self::instantForK($k);
            if ($instant->getTimestamp() > $from->getTimestamp()) {
                $index = ((int) round(($k - floor($k)) * 4.0)) % 4;
                $out[] = [
                    'type'    => self::PHASE_BY_INDEX[$index],
                    'instant' => $instant,
                ];
            }
            $k += 0.25;
        }

        return $out;
    }

    public static function translationKey(string $type): string
    {
        return self::META[$type]['key'];
    }

    public static function emoji(string $type): string
    {
        return self::META[$type]['emoji'];
    }

    private static function instantForK(float $k): DateTimeImmutable
    {
        $t  = $k / 1236.85;
        $t2 = $t * $t;
        $t3 = $t2 * $t;
        $t4 = $t3 * $t;

        // Mean phase (Meeus eq. 49.1).
        $jde = 2451550.09766
            + 29.530588861 * $k
            + 0.00015437 * $t2
            - 0.000000150 * $t3
            + 0.00000000073 * $t4;

        $e = 1.0 - 0.002516 * $t - 0.0000074 * $t2;          // eq. 47.6

        $m  = 2.5534 + 29.10535670 * $k                      // Sun's mean anomaly
            - 0.0000014 * $t2 - 0.00000011 * $t3;
        $mp = 201.5643 + 385.81693528 * $k                   // Moon's mean anomaly
            + 0.0107582 * $t2 + 0.00001238 * $t3 - 0.000000058 * $t4;
        $f  = 160.7108 + 390.67050284 * $k                   // Moon's argument of latitude
            - 0.0016118 * $t2 - 0.00000227 * $t3 + 0.000000011 * $t4;
        $omega = 124.7746 - 1.56375588 * $k                  // Longitude of ascending node
            + 0.0020672 * $t2 + 0.00000215 * $t3;

        $index = ((int) round(($k - floor($k)) * 4.0)) % 4;

        if ($index === 0 || $index === 2) {                  // new or full moon
            $jde += self::newOrFullCorrection($e, $m, $mp, $f, $omega);
        } else {                                             // first or last quarter
            $jde += self::quarterCorrection($e, $m, $mp, $f, $omega);
            $w = 0.00306
                - 0.00038 * $e * self::cosd($m)
                + 0.00026 * self::cosd($mp)
                - 0.00002 * self::cosd($mp - $m)
                + 0.00002 * self::cosd($mp + $m)
                + 0.00002 * self::cosd(2.0 * $f);
            $jde += ($index === 1) ? $w : -$w;               // +W first quarter, −W last
        }

        $timestamp = (int) round(($jde - 2440587.5) * 86400.0);

        return new DateTimeImmutable('@' . $timestamp);
    }

    private static function newOrFullCorrection(float $e, float $m, float $mp, float $f, float $omega): float
    {
        return -0.40720 * self::sind($mp)
            + 0.17241 * $e * self::sind($m)
            + 0.01608 * self::sind(2.0 * $mp)
            + 0.01039 * self::sind(2.0 * $f)
            + 0.00739 * $e * self::sind($mp - $m)
            - 0.00514 * $e * self::sind($mp + $m)
            + 0.00208 * $e * $e * self::sind(2.0 * $m)
            - 0.00111 * self::sind($mp - 2.0 * $f)
            - 0.00057 * self::sind($mp + 2.0 * $f)
            + 0.00056 * $e * self::sind(2.0 * $mp + $m)
            - 0.00042 * self::sind(3.0 * $mp)
            + 0.00042 * $e * self::sind($m + 2.0 * $f)
            + 0.00038 * $e * self::sind($m - 2.0 * $f)
            - 0.00024 * $e * self::sind(2.0 * $mp - $m)
            - 0.00017 * self::sind($omega)
            - 0.00007 * self::sind($mp + 2.0 * $m)
            + 0.00004 * self::sind(2.0 * $mp - 2.0 * $f)
            + 0.00004 * self::sind(3.0 * $m)
            + 0.00003 * self::sind($mp + $m - 2.0 * $f)
            + 0.00003 * self::sind(2.0 * $mp + 2.0 * $f)
            - 0.00003 * self::sind($mp + $m + 2.0 * $f)
            + 0.00003 * self::sind($mp - $m + 2.0 * $f)
            - 0.00002 * self::sind($mp - $m - 2.0 * $f)
            - 0.00002 * self::sind(3.0 * $mp + $m)
            + 0.00002 * self::sind(4.0 * $mp);
    }

    private static function quarterCorrection(float $e, float $m, float $mp, float $f, float $omega): float
    {
        return -0.62801 * self::sind($mp)
            + 0.17172 * $e * self::sind($m)
            - 0.01183 * $e * self::sind($mp + $m)
            + 0.00862 * self::sind(2.0 * $mp)
            + 0.00804 * self::sind(2.0 * $f)
            + 0.00454 * $e * self::sind($mp - $m)
            + 0.00204 * $e * $e * self::sind(2.0 * $m)
            - 0.00180 * self::sind($mp - 2.0 * $f)
            - 0.00070 * self::sind($mp + 2.0 * $f)
            - 0.00040 * self::sind(3.0 * $mp)
            - 0.00034 * $e * self::sind(2.0 * $mp - $m)
            + 0.00032 * $e * self::sind($m + 2.0 * $f)
            + 0.00032 * $e * self::sind($m - 2.0 * $f)
            - 0.00028 * $e * $e * self::sind($mp + 2.0 * $m)
            + 0.00027 * $e * self::sind(2.0 * $mp + $m)
            - 0.00017 * self::sind($omega)
            - 0.00005 * self::sind($mp - $m - 2.0 * $f)
            + 0.00004 * self::sind(2.0 * $mp + 2.0 * $f)
            - 0.00004 * self::sind($mp + $m + 2.0 * $f)
            + 0.00004 * self::sind($mp - 2.0 * $m)
            + 0.00003 * self::sind($mp + $m - 2.0 * $f)
            + 0.00003 * self::sind(3.0 * $m)
            + 0.00002 * self::sind(2.0 * $mp - 2.0 * $f)
            + 0.00002 * self::sind($mp - $m + 2.0 * $f)
            - 0.00002 * self::sind(3.0 * $mp + $m);
    }

    private static function sind(float $deg): float
    {
        return sin(deg2rad($deg));
    }

    private static function cosd(float $deg): float
    {
        return cos(deg2rad($deg));
    }
}
