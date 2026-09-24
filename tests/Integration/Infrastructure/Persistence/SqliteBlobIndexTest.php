<?php

declare(strict_types=1);

namespace Innis\Hubstr\Blossom\Tests\Integration\Infrastructure\Persistence;

use Innis\Hubstr\Blossom\Infrastructure\Persistence\SqliteBlobIndex;
use Innis\Hubstr\Core\Infrastructure\Persistence\SchemaMigrator;
use Innis\Hubstr\Core\Infrastructure\Persistence\SqliteDatabase;
use Innis\Nostr\Blossom\Domain\ValueObject\BlobDescriptor;
use Innis\Nostr\Blossom\Domain\ValueObject\BlobHash;
use Innis\Nostr\Blossom\Domain\ValueObject\HttpUrl;
use Innis\Nostr\Blossom\Domain\ValueObject\ListQuery;
use Innis\Nostr\Blossom\Domain\ValueObject\MimeType;
use Innis\Nostr\Core\Domain\ValueObject\Content\FileMetadata;
use Innis\Nostr\Core\Domain\ValueObject\Identity\PublicKey;
use Innis\Nostr\Core\Domain\ValueObject\Timestamp;
use PHPUnit\Framework\TestCase;

final class SqliteBlobIndexTest extends TestCase
{
    private SqliteBlobIndex $index;
    private PublicKey $tenant;

    protected function setUp(): void
    {
        $this->tenant = $this->pubkey(str_repeat('a', 64));
        $pdo = SqliteDatabase::inMemory()->connect();
        new SchemaMigrator($pdo)->migrate(dirname(__DIR__, 4).'/resources/migrations');
        $this->index = new SqliteBlobIndex($pdo);
    }

    public function testSaveAndFindByHash(): void
    {
        $descriptor = $this->makeDescriptor('content1');
        $this->index->save($this->tenant, $descriptor);

        $found = $this->index->findByHash($descriptor->getSha256());

        self::assertNotNull($found);
        self::assertSame($descriptor->getSha256()->toHex(), $found->getSha256()->toHex());
        self::assertSame((string) $descriptor->getUrl(), (string) $found->getUrl());
        self::assertSame($descriptor->getSize(), $found->getSize());
        self::assertSame((string) $descriptor->getType(), (string) $found->getType());
    }

    public function testFindByHashReturnsNullForMissing(): void
    {
        self::assertNull($this->index->findByHash($this->hash('missing')));
    }

    public function testDeleteForTenant(): void
    {
        $descriptor = $this->makeDescriptor('to_delete');
        $this->index->save($this->tenant, $descriptor);

        self::assertTrue($this->index->deleteForTenant($this->tenant, $descriptor->getSha256()));
        self::assertNull($this->index->findByHash($descriptor->getSha256()));
    }

    public function testDeleteNonExistentReturnsFalse(): void
    {
        self::assertFalse($this->index->deleteForTenant($this->tenant, $this->hash('nonexistent')));
    }

    public function testListReturnsDescriptorsSortedByUploadedDesc(): void
    {
        $this->index->save($this->tenant, $this->makeDescriptor('a', 1000));
        $this->index->save($this->tenant, $this->makeDescriptor('b', 3000));
        $this->index->save($this->tenant, $this->makeDescriptor('c', 2000));

        $results = $this->index->list($this->tenant, new ListQuery())->toArray();

        self::assertCount(3, $results);
        self::assertSame(3000, $results[0]->getUploaded()->toInt());
        self::assertSame(2000, $results[1]->getUploaded()->toInt());
        self::assertSame(1000, $results[2]->getUploaded()->toInt());
    }

    public function testListWithLimit(): void
    {
        $this->index->save($this->tenant, $this->makeDescriptor('a', 1000));
        $this->index->save($this->tenant, $this->makeDescriptor('b', 2000));
        $this->index->save($this->tenant, $this->makeDescriptor('c', 3000));

        $results = $this->index->list($this->tenant, new ListQuery(limit: 2))->toArray();

        self::assertCount(2, $results);
        self::assertSame(3000, $results[0]->getUploaded()->toInt());
        self::assertSame(2000, $results[1]->getUploaded()->toInt());
    }

    public function testListWithSinceFilter(): void
    {
        $this->index->save($this->tenant, $this->makeDescriptor('a', 1000));
        $this->index->save($this->tenant, $this->makeDescriptor('b', 2000));
        $this->index->save($this->tenant, $this->makeDescriptor('c', 3000));

        $results = $this->index->list($this->tenant, new ListQuery(since: Timestamp::fromInt(2000)))->toArray();

        self::assertCount(2, $results);
        self::assertSame(3000, $results[0]->getUploaded()->toInt());
        self::assertSame(2000, $results[1]->getUploaded()->toInt());
    }

    public function testListWithUntilFilter(): void
    {
        $this->index->save($this->tenant, $this->makeDescriptor('a', 1000));
        $this->index->save($this->tenant, $this->makeDescriptor('b', 2000));
        $this->index->save($this->tenant, $this->makeDescriptor('c', 3000));

        $results = $this->index->list($this->tenant, new ListQuery(until: Timestamp::fromInt(2000)))->toArray();

        self::assertCount(2, $results);
        self::assertSame(2000, $results[0]->getUploaded()->toInt());
        self::assertSame(1000, $results[1]->getUploaded()->toInt());
    }

    public function testTimestampCollisionTiebreaksOnSha256(): void
    {
        $this->index->save($this->tenant, $this->makeDescriptor('x', 1000));
        $this->index->save($this->tenant, $this->makeDescriptor('y', 1000));
        $this->index->save($this->tenant, $this->makeDescriptor('z', 1000));

        $results = $this->index->list($this->tenant, new ListQuery())->toArray();

        self::assertCount(3, $results);
        self::assertTrue($results[0]->getSha256()->toHex() > $results[1]->getSha256()->toHex());
        self::assertTrue($results[1]->getSha256()->toHex() > $results[2]->getSha256()->toHex());
    }

    public function testEmptyListReturnsEmptyArray(): void
    {
        self::assertSame([], $this->index->list($this->tenant, new ListQuery())->toArray());
    }

    public function testIsolatesAndDeduplicatesBlobsAcrossTenants(): void
    {
        $other = $this->pubkey(str_repeat('b', 64));
        $descriptor = $this->makeDescriptor('shared', 1000);

        $this->index->save($this->tenant, $descriptor);
        $this->index->save($other, $descriptor);

        self::assertCount(1, $this->index->list($this->tenant, new ListQuery()));
        self::assertCount(1, $this->index->list($other, new ListQuery()));

        self::assertTrue($this->index->deleteForTenant($this->tenant, $descriptor->getSha256()));
        self::assertCount(0, $this->index->list($this->tenant, new ListQuery()));
        self::assertCount(1, $this->index->list($other, new ListQuery()));
        self::assertNotNull($this->index->findByHash($descriptor->getSha256()));

        self::assertTrue($this->index->deleteForTenant($other, $descriptor->getSha256()));
        self::assertNull($this->index->findByHash($descriptor->getSha256()));
    }

    public function testPersistsAndHydratesNip94Metadata(): void
    {
        $sha256 = hash('sha256', 'image');
        $url = 'https://test.com/'.$sha256.'.png';
        $descriptor = new BlobDescriptor(
            url: $this->httpUrl($url),
            sha256: $this->blobHash($sha256),
            size: 2048,
            type: MimeType::fromString('image/png'),
            uploaded: Timestamp::fromInt(1000),
            nip94: new FileMetadata(
                url: $url,
                mimeType: 'image/png',
                hash: $sha256,
                size: 2048,
                dimensions: '640x480',
                blurhash: 'LEHV6nWB2yk8',
            ),
        );

        $this->index->save($this->tenant, $descriptor);
        $found = $this->index->findByHash($descriptor->getSha256());

        self::assertNotNull($found);
        $nip94 = $found->getNip94();
        self::assertNotNull($nip94);
        self::assertSame('640x480', $nip94->getDimensions());
        self::assertSame('LEHV6nWB2yk8', $nip94->getBlurhash());
        self::assertSame($sha256, $nip94->getHash());
    }

    private function makeDescriptor(string $seed, int $uploaded = 1000): BlobDescriptor
    {
        $sha256 = hash('sha256', $seed);

        return new BlobDescriptor(
            url: $this->httpUrl('https://test.com/'.$sha256.'.png'),
            sha256: $this->blobHash($sha256),
            size: 1024,
            type: MimeType::fromString('image/png'),
            uploaded: Timestamp::fromInt($uploaded),
        );
    }

    private function httpUrl(string $value): HttpUrl
    {
        $url = HttpUrl::tryFromString($value);
        self::assertNotNull($url);

        return $url;
    }

    private function blobHash(string $hex): BlobHash
    {
        $hash = BlobHash::tryFromHex($hex);
        self::assertNotNull($hash);

        return $hash;
    }

    private function hash(string $seed): BlobHash
    {
        return $this->blobHash(hash('sha256', $seed));
    }

    private function pubkey(string $hex): PublicKey
    {
        $pubkey = PublicKey::tryFromHex($hex);
        self::assertNotNull($pubkey);

        return $pubkey;
    }
}
