<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Service\Observation\Climate\PdsiLevel;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PdsiLevelTest extends TestCase
{
    #[DataProvider('values')]
    public function testFromValueMapsToBand(float $value, int $expected): void
    {
        self::assertSame($expected, PdsiLevel::fromValue($value));
    }

    /**
     * @return iterable<string, array{0: float, 1: int}>
     */
    public static function values(): iterable
    {
        yield 'extreme drought edge'    => [-4.0,  PdsiLevel::EXTREME_DROUGHT];
        yield 'severe drought lower'    => [-3.99, PdsiLevel::SEVERE_DROUGHT];
        yield 'severe drought edge'     => [-3.0,  PdsiLevel::SEVERE_DROUGHT];
        yield 'moderate drought lower'  => [-2.99, PdsiLevel::MODERATE_DROUGHT];
        yield 'moderate drought edge'   => [-2.0,  PdsiLevel::MODERATE_DROUGHT];
        yield 'mild drought lower'      => [-1.99, PdsiLevel::MILD_DROUGHT];
        yield 'mild drought edge'       => [-1.0,  PdsiLevel::MILD_DROUGHT];
        yield 'near normal lower'       => [-0.99, PdsiLevel::NEAR_NORMAL];
        yield 'near normal zero'        => [0.0,   PdsiLevel::NEAR_NORMAL];
        yield 'near normal upper'       => [0.99,  PdsiLevel::NEAR_NORMAL];
        yield 'slightly wet lower'      => [1.0,   PdsiLevel::SLIGHTLY_WET];
        yield 'slightly wet upper'      => [1.99,  PdsiLevel::SLIGHTLY_WET];
        yield 'moderately wet lower'    => [2.0,   PdsiLevel::MODERATELY_WET];
        yield 'moderately wet upper'    => [2.99,  PdsiLevel::MODERATELY_WET];
        yield 'very wet lower'          => [3.0,   PdsiLevel::VERY_WET];
        yield 'very wet upper'          => [3.99,  PdsiLevel::VERY_WET];
        yield 'extremely wet lower'     => [4.0,   PdsiLevel::EXTREMELY_WET];
        yield 'extremely wet high'      => [9.0,   PdsiLevel::EXTREMELY_WET];
    }

    public function testLabelsAndColours(): void
    {
        foreach (PdsiLevel::all() as $level) {
            self::assertNotSame('', PdsiLevel::label($level));
            self::assertMatchesRegularExpression('/^#[0-9a-fA-F]{6}$/', PdsiLevel::color($level));
            self::assertMatchesRegularExpression('/^#[0-9a-fA-F]{6}$/', PdsiLevel::textColor($level));
        }
    }

    public function testAllIsAscendingSeverity(): void
    {
        $levels = PdsiLevel::all();
        $sorted = $levels;
        sort($sorted);
        self::assertSame($sorted, $levels);
        self::assertCount(9, $levels);
    }
}
