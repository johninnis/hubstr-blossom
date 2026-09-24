<?php

declare(strict_types=1);

namespace Innis\Hubstr\Blossom\Tests\Unit\Presentation\Cli;

use Innis\Hubstr\Blossom\Presentation\Cli\ListOptionFailure;
use Innis\Hubstr\Blossom\Presentation\Cli\ListOptions;
use Innis\Nostr\Blossom\Domain\ValueObject\ListQuery;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ListOptionsTest extends TestCase
{
    public function testNoOptionsListTheDefaultPage(): void
    {
        $this->assertEquals(new ListQuery(), ListOptions::toQuery([]));
    }

    public function testTheLimitIsParsedToAQuery(): void
    {
        $query = self::accepted(ListOptions::toQuery(['limit' => '50']));

        $this->assertSame(50, $query->getLimit());
    }

    /**
     * @param array<string, mixed> $options
     */
    #[DataProvider('malformedOptions')]
    public function testAMalformedOptionIsRefusedByName(array $options, string $message): void
    {
        $failure = ListOptions::toQuery($options);

        $this->assertInstanceOf(ListOptionFailure::class, $failure);
        $this->assertSame($message, $failure->getMessage());
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string}>
     */
    public static function malformedOptions(): iterable
    {
        yield 'a limit that is not a number' => [['limit' => 'abc'], 'Invalid --limit: abc'];
        yield 'a zero limit' => [['limit' => '0'], 'Invalid --limit: 0'];
        yield 'a limit above the page ceiling' => [['limit' => '1001'], 'Invalid --limit: 1001'];
        yield 'a repeated option' => [['limit' => ['1', '7']], 'Invalid --limit: expected a single value'];
    }

    private static function accepted(ListQuery|ListOptionFailure $outcome): ListQuery
    {
        self::assertInstanceOf(ListQuery::class, $outcome);

        return $outcome;
    }
}
