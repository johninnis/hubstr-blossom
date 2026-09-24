<?php

declare(strict_types=1);

namespace Innis\Hubstr\Blossom\Tests\Unit\Presentation\Http;

use Innis\Hubstr\Blossom\Presentation\Http\ByteRange;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ByteRangeTest extends TestCase
{
    public function testAbsentHeaderYieldsNoRange(): void
    {
        self::assertNull(ByteRange::parse(null, 100));
    }

    #[DataProvider('ignoredHeaders')]
    public function testUnparseableHeaderYieldsNoRange(string $header): void
    {
        self::assertNull(ByteRange::parse($header, 100));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function ignoredHeaders(): array
    {
        return [
            'no unit' => ['0-10'],
            'wrong unit' => ['items=0-10'],
            'no dash' => ['bytes=10'],
            'non-numeric start' => ['bytes=abc-10'],
            'non-numeric end' => ['bytes=5-abc'],
            'multi-range' => ['bytes=0-3,5-9'],
        ];
    }

    public function testClosedRangeIsParsed(): void
    {
        $range = ByteRange::parse('bytes=0-3', 100);

        self::assertNotNull($range);
        self::assertTrue($range->isSatisfiable());
        self::assertSame(0, $range->getStart());
        self::assertSame(4, $range->getLength());
    }

    public function testOpenEndedRangeRunsToEndOfFile(): void
    {
        $range = ByteRange::parse('bytes=90-', 100);

        self::assertNotNull($range);
        self::assertTrue($range->isSatisfiable());
        self::assertSame(90, $range->getStart());
        self::assertSame(10, $range->getLength());
    }

    public function testSuffixRangeReturnsLastBytes(): void
    {
        $range = ByteRange::parse('bytes=-25', 100);

        self::assertNotNull($range);
        self::assertTrue($range->isSatisfiable());
        self::assertSame(75, $range->getStart());
        self::assertSame(25, $range->getLength());
    }

    public function testEndIsClampedToFinalByte(): void
    {
        $range = ByteRange::parse('bytes=0-999', 100);

        self::assertNotNull($range);
        self::assertSame(0, $range->getStart());
        self::assertSame(100, $range->getLength());
    }

    public function testSuffixLargerThanFileReturnsWholeFile(): void
    {
        $range = ByteRange::parse('bytes=-999', 100);

        self::assertNotNull($range);
        self::assertSame(0, $range->getStart());
        self::assertSame(100, $range->getLength());
    }

    #[DataProvider('unsatisfiableHeaders')]
    public function testOutOfBoundsRangeIsUnsatisfiable(string $header): void
    {
        $range = ByteRange::parse($header, 100);

        self::assertNotNull($range);
        self::assertFalse($range->isSatisfiable());
    }

    /**
     * @return array<string, array{string}>
     */
    public static function unsatisfiableHeaders(): array
    {
        return [
            'start past end' => ['bytes=100-200'],
            'zero-length suffix' => ['bytes=-0'],
            'end before start' => ['bytes=20-10'],
        ];
    }
}
