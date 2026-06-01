<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Service\Astronomy\MoonPhase;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;

final class MoonPhaseTest extends TestCase
{
    private const UTC_FMT = 'Y-m-d H:i:s';

    private static function utc(string $iso): DateTimeImmutable
    {
        return new DateTimeImmutable($iso, new DateTimeZone('UTC'));
    }

    /**
     * Independently-known almanac anchor: the New Moon of 1977-02-18 occurred
     * at 03:37 UTC. Meeus ch. 49 yields Dynamical Time (~03:38, ΔT≈48s ahead of
     * UTC in 1977), which we treat as UTC — so we expect within minutes of 03:37.
     */
    public function testMatchesKnownNewMoon(): void
    {
        $from = self::utc('1977-02-15 00:00:00');

        $phases = MoonPhase::nextPrincipalPhases($from, 4);

        self::assertSame(MoonPhase::NEW_MOON, $phases[0]['type']);

        $target = self::utc('1977-02-18 03:37:00')->getTimestamp();
        $actual = $phases[0]['instant']->getTimestamp();
        self::assertLessThanOrEqual(
            900,
            abs($actual - $target),
            'New moon instant within 15 min of the Meeus worked example, got '
                . $phases[0]['instant']->setTimezone(new DateTimeZone('UTC'))->format(self::UTC_FMT),
        );
    }

    public function testReturnsRequestedCount(): void
    {
        $from = self::utc('2026-06-01 00:00:00');

        self::assertCount(4, MoonPhase::nextPrincipalPhases($from, 4));
        self::assertCount(2, MoonPhase::nextPrincipalPhases($from, 2));
    }

    public function testEventsAreStrictlyIncreasingAndSpacedLikeQuarterLunations(): void
    {
        $from = self::utc('2026-06-01 00:00:00');

        $phases = MoonPhase::nextPrincipalPhases($from, 4);

        $prev = $from->getTimestamp();
        foreach ($phases as $phase) {
            $ts = $phase['instant']->getTimestamp();
            self::assertGreaterThan($prev, $ts, 'instants strictly increasing');
            $gapDays = ($ts - $prev) / 86400.0;
            self::assertGreaterThan(5.0, $gapDays, 'quarter-lunation gap > 5 days');
            self::assertLessThan(9.0, $gapDays, 'quarter-lunation gap < 9 days');
            $prev = $ts;
        }
    }

    public function testTypesCycleInOrderStartingFromTheFirstEvent(): void
    {
        // Starting just before a New Moon, the next four cycle new → first → full → last.
        $from = self::utc('1977-02-15 00:00:00');

        $types = array_column(MoonPhase::nextPrincipalPhases($from, 4), 'type');

        self::assertSame(
            [MoonPhase::NEW_MOON, MoonPhase::FIRST_QUARTER, MoonPhase::FULL_MOON, MoonPhase::LAST_QUARTER],
            $types,
        );
    }

    public function testTranslationKeyAndEmojiMapping(): void
    {
        self::assertSame('location.moon_phase_new', MoonPhase::translationKey(MoonPhase::NEW_MOON));
        self::assertSame('location.moon_phase_first_quarter', MoonPhase::translationKey(MoonPhase::FIRST_QUARTER));
        self::assertSame('location.moon_phase_full', MoonPhase::translationKey(MoonPhase::FULL_MOON));
        self::assertSame('location.moon_phase_last_quarter', MoonPhase::translationKey(MoonPhase::LAST_QUARTER));

        self::assertSame('🌑', MoonPhase::emoji(MoonPhase::NEW_MOON));
        self::assertSame('🌕', MoonPhase::emoji(MoonPhase::FULL_MOON));
    }
}
