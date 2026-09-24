<?php

declare(strict_types=1);

namespace Innis\Hubstr\Blossom\Tests\Unit\Infrastructure\Persistence;

use Innis\Hubstr\Blossom\Infrastructure\Persistence\CachingBlobIndex;
use Innis\Nostr\Blossom\Application\Port\BlobIndexInterface;
use Innis\Nostr\Blossom\Domain\Collection\BlobDescriptorCollection;
use Innis\Nostr\Blossom\Domain\ValueObject\BlobDescriptor;
use Innis\Nostr\Blossom\Domain\ValueObject\BlobHash;
use Innis\Nostr\Blossom\Domain\ValueObject\HttpUrl;
use Innis\Nostr\Blossom\Domain\ValueObject\ListQuery;
use Innis\Nostr\Blossom\Domain\ValueObject\MimeType;
use Innis\Nostr\Core\Domain\ValueObject\Identity\PublicKey;
use Innis\Nostr\Core\Domain\ValueObject\Timestamp;
use PHPUnit\Framework\TestCase;

final class CachingBlobIndexTest extends TestCase
{
    private PublicKey $tenant;

    protected function setUp(): void
    {
        $this->tenant = $this->pubkey(str_repeat('a', 64));
    }

    public function testFindByHashQueriesDelegateOnceThenServesFromCache(): void
    {
        $descriptor = $this->makeDescriptor('cached');
        $delegate = $this->createMock(BlobIndexInterface::class);
        $delegate->expects(self::once())
            ->method('findByHash')
            ->with($descriptor->getSha256())
            ->willReturn($descriptor);

        $index = new CachingBlobIndex($delegate);

        self::assertSame($descriptor, $index->findByHash($descriptor->getSha256()));
        self::assertSame($descriptor, $index->findByHash($descriptor->getSha256()));
    }

    public function testMissingResultIsNotCached(): void
    {
        $hash = $this->hash('missing');
        $delegate = $this->createMock(BlobIndexInterface::class);
        $delegate->expects(self::exactly(2))
            ->method('findByHash')
            ->with($hash)
            ->willReturn(null);

        $index = new CachingBlobIndex($delegate);

        self::assertNull($index->findByHash($hash));
        self::assertNull($index->findByHash($hash));
    }

    public function testSavePopulatesCacheWithoutQueryingDelegate(): void
    {
        $descriptor = $this->makeDescriptor('saved');
        $delegate = $this->createMock(BlobIndexInterface::class);
        $delegate->expects(self::once())->method('save')->with($this->tenant, $descriptor);
        $delegate->expects(self::never())->method('findByHash');

        $index = new CachingBlobIndex($delegate);
        $index->save($this->tenant, $descriptor);

        self::assertSame($descriptor, $index->findByHash($descriptor->getSha256()));
    }

    public function testDeleteEvictsCachedDescriptor(): void
    {
        $descriptor = $this->makeDescriptor('evicted');
        $delegate = $this->createMock(BlobIndexInterface::class);
        $delegate->method('save');
        $delegate->method('deleteForTenant')->willReturn(true);
        $delegate->expects(self::once())
            ->method('findByHash')
            ->with($descriptor->getSha256())
            ->willReturn($descriptor);

        $index = new CachingBlobIndex($delegate);
        $index->save($this->tenant, $descriptor);
        $index->deleteForTenant($this->tenant, $descriptor->getSha256());

        self::assertSame($descriptor, $index->findByHash($descriptor->getSha256()));
    }

    public function testOwnsBlobDelegatesToIndex(): void
    {
        $hash = $this->hash('owned');
        $delegate = $this->createMock(BlobIndexInterface::class);
        $delegate->expects(self::once())
            ->method('ownsBlob')
            ->with($this->tenant, $hash)
            ->willReturn(true);

        $index = new CachingBlobIndex($delegate);

        self::assertTrue($index->ownsBlob($this->tenant, $hash));
    }

    public function testListDelegatesWithoutCaching(): void
    {
        $collection = new BlobDescriptorCollection([]);
        $delegate = $this->createMock(BlobIndexInterface::class);
        $delegate->expects(self::once())->method('list')->willReturn($collection);

        $index = new CachingBlobIndex($delegate);

        self::assertSame($collection, $index->list($this->tenant, new ListQuery()));
    }

    private function makeDescriptor(string $seed): BlobDescriptor
    {
        $sha256 = hash('sha256', $seed);

        return new BlobDescriptor(
            url: $this->httpUrl('https://test.com/'.$sha256.'.png'),
            sha256: $this->blobHash($sha256),
            size: 1024,
            type: MimeType::fromString('image/png'),
            uploaded: Timestamp::fromInt(1000),
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
