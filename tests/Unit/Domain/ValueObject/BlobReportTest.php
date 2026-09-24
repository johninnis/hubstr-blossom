<?php

declare(strict_types=1);

namespace Innis\Hubstr\Blossom\Tests\Unit\Domain\ValueObject;

use Innis\Hubstr\Blossom\Domain\ValueObject\BlobReport;
use Innis\Nostr\Blossom\Domain\ValueObject\BlobHash;
use Innis\Nostr\Core\Domain\Entity\Event;
use Innis\Nostr\Core\Domain\ValueObject\Content\EventKind;
use Innis\Nostr\Core\Domain\ValueObject\Timestamp;
use PHPUnit\Framework\TestCase;

final class BlobReportTest extends TestCase
{
    public function testTheTypeComesFromTheXTagNamingTheReportedBlob(): void
    {
        $blob = $this->hash('reported');

        $report = new BlobReport($blob, $this->event([['x', $this->hash('other')->toHex(), 'nudity'], ['x', $blob->toHex(), 'spam']]), Timestamp::fromInt(1000));

        self::assertSame('spam', $report->getType());
    }

    public function testTheTypeIsAbsentWhenTheNamingTagCarriesNone(): void
    {
        $blob = $this->hash('reported');

        $report = new BlobReport($blob, $this->event([['x', $blob->toHex()]]), Timestamp::fromInt(1000));

        self::assertNull($report->getType());
    }

    public function testTheReporterAndReasonAreReadFromTheEvent(): void
    {
        $event = $this->event([['x', $this->hash('reported')->toHex(), 'malware']]);

        $report = new BlobReport($this->hash('reported'), $event, Timestamp::fromInt(1000));

        self::assertSame([$event->getPubkey()->toHex(), 'This blob violates policy'], [$report->getReporter()->toHex(), $report->getReason()]);
    }

    /**
     * @param list<list<string>> $tags
     */
    private function event(array $tags): Event
    {
        $event = Event::tryFromArray([
            'id' => str_repeat('a', 64),
            'pubkey' => str_repeat('b', 64),
            'created_at' => 1000,
            'kind' => EventKind::REPORTING,
            'tags' => $tags,
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
