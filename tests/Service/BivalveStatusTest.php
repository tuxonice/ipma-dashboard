<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Service\Observation\Biology\BivalveStatus;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class BivalveStatusTest extends TestCase
{
    #[DataProvider('knownStatuses')]
    public function testNormaliseAndLabel(string $raw, string $expected, string $label, string $bootstrap): void
    {
        self::assertSame($expected, BivalveStatus::normalise($raw));
        self::assertSame($label, BivalveStatus::label($raw));
        self::assertSame($bootstrap, BivalveStatus::bootstrap($raw));
        self::assertMatchesRegularExpression('/^#[0-9a-fA-F]{6}$/', BivalveStatus::color($raw));
        self::assertMatchesRegularExpression('/^#[0-9a-fA-F]{6}$/', BivalveStatus::textColor($raw));
    }

    /**
     * @return iterable<string, array{0:string,1:string,2:string,3:string}>
     */
    public static function knownStatuses(): iterable
    {
        yield 'open'           => ['OPEN',           BivalveStatus::OPEN,          'bivalves.status.open',          'success'];
        yield 'lowercase open' => ['open',           BivalveStatus::OPEN,          'bivalves.status.open',          'success'];
        yield 'partial open'   => ['PARTIAL_OPEN',   BivalveStatus::PARTIAL_OPEN,  'bivalves.status.partial_open',  'warning'];
        yield 'partial close'  => ['PARTIAL_CLOSE',  BivalveStatus::PARTIAL_CLOSE, 'bivalves.status.partial_close', 'warning'];
        yield 'close'          => ['CLOSE',          BivalveStatus::CLOSE,         'bivalves.status.close',         'danger'];
        yield 'whitespace'     => ['  open  ',       BivalveStatus::OPEN,          'bivalves.status.open',          'success'];
    }

    public function testUnknownStatusFallsBack(): void
    {
        self::assertSame(BivalveStatus::UNKNOWN, BivalveStatus::normalise('foo'));
        self::assertSame('bivalves.status.unknown', BivalveStatus::label('foo'));
        self::assertSame('secondary', BivalveStatus::bootstrap('foo'));
    }

    public function testAllExcludesUnknown(): void
    {
        self::assertNotContains(BivalveStatus::UNKNOWN, BivalveStatus::all());
        self::assertCount(4, BivalveStatus::all());
    }
}
