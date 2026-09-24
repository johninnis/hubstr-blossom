<?php

declare(strict_types=1);

namespace Innis\Hubstr\Blossom\Tests\Acceptance\BudCompliance;

use Innis\Hubstr\Blossom\Tests\Support\BlossomTestServer;
use Innis\Nostr\Core\Domain\Collection\TagCollection;
use Innis\Nostr\Core\Domain\Factory\RumourFactory;
use Innis\Nostr\Core\Domain\ValueObject\Content\EventContent;
use Innis\Nostr\Core\Domain\ValueObject\Content\EventKind;
use Innis\Nostr\Core\Domain\ValueObject\Identity\KeyPair;
use Innis\Nostr\Core\Domain\ValueObject\Tag\Tag;
use Innis\Nostr\Core\Domain\ValueObject\Tag\TagType;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;

final class Bud09Test extends TestCase
{
    private static BlossomTestServer $server;

    public static function setUpBeforeClass(): void
    {
        self::$server = BlossomTestServer::boot();
    }

    public static function tearDownAfterClass(): void
    {
        self::$server->stop();
    }

    #[TestDox('BUD-09 — PUT /report accepts a signed kind-1984 report event carrying x tags and responds with success')]
    public function testReportAcceptsValidKind1984Event(): void
    {
        $keyPair = KeyPair::generate(self::$server->signer());
        $tags = new TagCollection([
            new Tag(TagType::sha256(), [hash('sha256', 'reported blob')]),
        ]);

        $event = RumourFactory::createCustomKind(
            $keyPair->getPublicKey(),
            EventKind::fromInt(EventKind::REPORTING),
            EventContent::fromString('This blob violates policy'),
            $tags,
        )->sign($keyPair, self::$server->signer());

        $response = self::$server->request('PUT', '/report', [], (string) json_encode($event->toArray()));

        self::assertSame(200, $response->status());
    }

    #[TestDox('BUD-09 — A report event with an invalid signature is rejected')]
    public function testReportRejectsInvalidSignature(): void
    {
        $response = self::$server->request('PUT', '/report', [], (string) json_encode([
            'id' => str_repeat('a', 64),
            'pubkey' => str_repeat('b', 64),
            'created_at' => time(),
            'kind' => EventKind::REPORTING,
            'tags' => [['x', hash('sha256', 'blob')]],
            'content' => 'report',
            'sig' => str_repeat('c', 128),
        ]));

        self::assertSame(400, $response->status());
    }

    #[TestDox('BUD-09 — A report event of the wrong kind is rejected')]
    public function testReportRejectsWrongKind(): void
    {
        $keyPair = KeyPair::generate(self::$server->signer());

        $event = RumourFactory::createCustomKind(
            $keyPair->getPublicKey(),
            EventKind::fromInt(EventKind::TEXT_NOTE),
            EventContent::fromString('Not a report'),
            new TagCollection([new Tag(TagType::sha256(), [hash('sha256', 'blob')])]),
        )->sign($keyPair, self::$server->signer());

        $response = self::$server->request('PUT', '/report', [], (string) json_encode($event->toArray()));

        self::assertSame(400, $response->status());
    }

    #[TestDox('BUD-09 — A report event without x tags identifying blobs is rejected')]
    public function testReportRejectsEventWithoutXTags(): void
    {
        $keyPair = KeyPair::generate(self::$server->signer());

        $event = RumourFactory::createCustomKind(
            $keyPair->getPublicKey(),
            EventKind::fromInt(EventKind::REPORTING),
            EventContent::fromString('No x tags'),
            new TagCollection(),
        )->sign($keyPair, self::$server->signer());

        $response = self::$server->request('PUT', '/report', [], (string) json_encode($event->toArray()));

        self::assertSame(400, $response->status());
    }
}
