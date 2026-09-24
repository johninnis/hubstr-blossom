<?php

declare(strict_types=1);

namespace Innis\Hubstr\Blossom\Tests\Integration\Infrastructure\Persistence;

use Innis\Hubstr\Blossom\Domain\Exception\CorruptRowException;
use Innis\Hubstr\Blossom\Domain\ValueObject\BlobReport;
use Innis\Hubstr\Blossom\Infrastructure\Persistence\SqliteBlobReportStore;
use Innis\Hubstr\Core\Infrastructure\Persistence\SchemaMigrator;
use Innis\Hubstr\Core\Infrastructure\Persistence\SqliteDatabase;
use Innis\Nostr\Blossom\Domain\ValueObject\BlobHash;
use Innis\Nostr\Blossom\Domain\ValueObject\ListQuery;
use Innis\Nostr\Core\Application\Port\ClockInterface;
use Innis\Nostr\Core\Domain\Entity\Event;
use Innis\Nostr\Core\Domain\ValueObject\Content\EventKind;
use Innis\Nostr\Core\Domain\ValueObject\Timestamp;
use PDO;
use PHPUnit\Framework\TestCase;

final class SqliteBlobReportStoreTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        $this->pdo = SqliteDatabase::inMemory()->connect();
        new SchemaMigrator($this->pdo)->migrate(dirname(__DIR__, 4).'/resources/migrations');
    }

    public function testASavedReportIsListedWithTheTimeItWasReceived(): void
    {
        $hash = $this->hash('reported');
        $this->storeAt(1000)->save($hash, $this->event($hash, 'spam'));

        $reports = $this->storeAt(2000)->list(new ListQuery())->toArray();

        self::assertCount(1, $reports);
        self::assertSame([$hash->toHex(), 'spam', 1000], [$reports[0]->getBlob()->toHex(), $reports[0]->getType(), $reports[0]->getReceivedAt()->toInt()]);
    }

    public function testReportsAreListedNewestFirst(): void
    {
        $this->storeAt(1000)->save($this->hash('a'), $this->event($this->hash('a'), 'spam'));
        $this->storeAt(3000)->save($this->hash('b'), $this->event($this->hash('b'), 'spam'));
        $this->storeAt(2000)->save($this->hash('c'), $this->event($this->hash('c'), 'spam'));

        $received = array_map(static fn (BlobReport $report): int => $report->getReceivedAt()->toInt(), $this->storeAt(0)->list(new ListQuery())->toArray());

        self::assertSame([3000, 2000, 1000], $received);
    }

    public function testTheListHonoursLimitSinceAndUntil(): void
    {
        foreach ([1000, 2000, 3000, 4000] as $at) {
            $this->storeAt($at)->save($this->hash((string) $at), $this->event($this->hash((string) $at), 'spam'));
        }

        $windowed = $this->storeAt(0)->list(new ListQuery(limit: 2, since: Timestamp::fromInt(2000), until: Timestamp::fromInt(4000)))->toArray();

        self::assertSame([4000, 3000], array_map(static fn (BlobReport $report): int => $report->getReceivedAt()->toInt(), $windowed));
    }

    public function testARowWhoseEventNoLongerParsesIsAFault(): void
    {
        $this->pdo->exec("INSERT INTO reports (blob_sha256, event_json, created_at) VALUES ('".str_repeat('a', 64)."', '{}', 1000)");

        $this->expectException(CorruptRowException::class);
        $this->expectExceptionMessage('Stored reports row 1 holds an event that no longer parses');

        $this->storeAt(0)->list(new ListQuery());
    }

    private function storeAt(int $now): SqliteBlobReportStore
    {
        $clock = $this->createStub(ClockInterface::class);
        $clock->method('now')->willReturn(Timestamp::fromInt($now));

        return new SqliteBlobReportStore($this->pdo, $clock);
    }

    private function event(BlobHash $hash, string $type): Event
    {
        $event = Event::tryFromArray([
            'id' => hash('sha256', 'id'.$hash->toHex()),
            'pubkey' => str_repeat('b', 64),
            'created_at' => 1000,
            'kind' => EventKind::REPORTING,
            'tags' => [['x', $hash->toHex(), $type]],
            'content' => 'This blob violates policy',
            'sig' => str_repeat('c', 128),
        ]);
        self::assertNotNull($event);

        return $event;
    }

    private function hash(string $seed): BlobHash
    {
        $hash = BlobHash::tryFromHex(hash('sha256', $seed));
        self::assertNotNull($hash);

        return $hash;
    }
}
