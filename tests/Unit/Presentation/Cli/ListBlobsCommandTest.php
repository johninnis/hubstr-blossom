<?php

declare(strict_types=1);

namespace Innis\Hubstr\Blossom\Tests\Unit\Presentation\Cli;

use Innis\Hubstr\Blossom\Presentation\Cli\ListBlobsCommand;
use Innis\Nostr\Blossom\Application\Port\BlobIndexInterface;
use Innis\Nostr\Blossom\Domain\Collection\BlobDescriptorCollection;
use Innis\Nostr\Blossom\Domain\ValueObject\BlobDescriptor;
use Innis\Nostr\Blossom\Domain\ValueObject\BlobHash;
use Innis\Nostr\Blossom\Domain\ValueObject\HttpUrl;
use Innis\Nostr\Blossom\Domain\ValueObject\ListQuery;
use Innis\Nostr\Blossom\Domain\ValueObject\MimeType;
use Innis\Nostr\Blossom\Domain\ValueObject\TenantPubkeys;
use Innis\Nostr\Core\Domain\ValueObject\Identity\PublicKey;
use Innis\Nostr\Core\Domain\ValueObject\Timestamp;
use PHPUnit\Framework\TestCase;

final class ListBlobsCommandTest extends TestCase
{
    public function testReportsStoredBlobsPerTenant(): void
    {
        $tenant = $this->tenant();
        $descriptor = $this->descriptor();

        $index = $this->createStub(BlobIndexInterface::class);
        $index->method('list')->willReturn(new BlobDescriptorCollection([$descriptor]));

        $report = new ListBlobsCommand($index, new TenantPubkeys([$tenant]))->run(new ListQuery(limit: 50));

        self::assertStringContainsString('Tenant '.$tenant->toHex().', 1 blob(s), newest first:', $report);
        self::assertStringContainsString($descriptor->getSha256()->toHex(), $report);
        self::assertStringContainsString('image/png', $report);
    }

    public function testReportsAbsenceWhenNoBlobsStored(): void
    {
        $index = $this->createStub(BlobIndexInterface::class);
        $index->method('list')->willReturn(new BlobDescriptorCollection([]));

        $report = new ListBlobsCommand($index, new TenantPubkeys([$this->tenant()]))->run(new ListQuery(limit: 50));

        self::assertSame("No blobs stored.\n", $report);
    }

    private function tenant(): PublicKey
    {
        $pubkey = PublicKey::tryFromHex(hash('sha256', 'tenant'));
        self::assertNotNull($pubkey);

        return $pubkey;
    }

    private function descriptor(): BlobDescriptor
    {
        $hash = BlobHash::tryFromHex(hash('sha256', 'blob'));
        self::assertNotNull($hash);

        $url = HttpUrl::tryFromString('https://blossom.example.com/'.$hash->toHex());
        self::assertNotNull($url);

        return new BlobDescriptor(
            url: $url,
            sha256: $hash,
            size: 2048,
            type: MimeType::fromString('image/png'),
            uploaded: Timestamp::fromInt(1_700_000_000),
            nip94: null,
        );
    }
}
