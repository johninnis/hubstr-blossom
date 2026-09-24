<?php

declare(strict_types=1);

namespace Innis\Hubstr\Blossom\Tests\Unit\Presentation\Cli;

use Innis\Hubstr\Blossom\Application\Port\BlobReportQueryInterface;
use Innis\Hubstr\Blossom\Domain\Collection\BlobReportCollection;
use Innis\Hubstr\Blossom\Domain\ValueObject\BlobReport;
use Innis\Hubstr\Blossom\Presentation\Cli\ListReportsCommand;
use Innis\Nostr\Blossom\Domain\ValueObject\BlobHash;
use Innis\Nostr\Blossom\Domain\ValueObject\ListQuery;
use Innis\Nostr\Core\Domain\Entity\Event;
use Innis\Nostr\Core\Domain\ValueObject\Content\EventKind;
use Innis\Nostr\Core\Domain\ValueObject\Timestamp;
use PHPUnit\Framework\TestCase;

final class ListReportsCommandTest extends TestCase
{
    public function testReportsEachStoredReportOnOneLine(): void
    {
        $report = $this->report("Spam\nposted twice");
        $query = $this->createStub(BlobReportQueryInterface::class);
        $query->method('list')->willReturn(new BlobReportCollection([$report]));

        $output = new ListReportsCommand($query)->run(new ListQuery(limit: 50));

        self::assertStringStartsWith("1 report(s), newest first:\n", $output);
        self::assertStringContainsString('1970-01-01T00:16:40+00:00  '.$report->getBlob()->toHex().'  spam            '.$report->getReporter()->toBech32().'  Spam posted twice', $output);
    }

    public function testReportsAbsenceWhenNothingIsStored(): void
    {
        $query = $this->createStub(BlobReportQueryInterface::class);
        $query->method('list')->willReturn(new BlobReportCollection([]));

        self::assertSame("No reports stored.\n", new ListReportsCommand($query)->run(new ListQuery(limit: 50)));
    }

    private function report(string $reason): BlobReport
    {
        $hash = BlobHash::tryFromHex(hash('sha256', 'reported'));
        self::assertNotNull($hash);

        $event = Event::tryFromArray([
            'id' => str_repeat('a', 64),
            'pubkey' => str_repeat('b', 64),
            'created_at' => 1000,
            'kind' => EventKind::REPORTING,
            'tags' => [['x', $hash->toHex(), 'spam']],
            'content' => $reason,
            'sig' => str_repeat('c', 128),
        ]);
        self::assertNotNull($event);

        return new BlobReport($hash, $event, Timestamp::fromInt(1000));
    }
}
