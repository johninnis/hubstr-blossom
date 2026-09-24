<?php

declare(strict_types=1);

namespace Innis\Hubstr\Blossom\Tests\Integration\Infrastructure\Filesystem;

use Innis\Hubstr\Blossom\Infrastructure\Filesystem\FilesystemBlobStore;
use Innis\Hubstr\Blossom\Tests\Support\TemporaryDirectory;
use Innis\Nostr\Blossom\Domain\ValueObject\BlobHash;
use PHPUnit\Framework\TestCase;

final class FilesystemBlobStoreTest extends TestCase
{
    private TemporaryDirectory $storage;

    private FilesystemBlobStore $store;

    protected function setUp(): void
    {
        $this->storage = TemporaryDirectory::create();
        $this->store = new FilesystemBlobStore($this->storage->getPath());
    }

    protected function tearDown(): void
    {
        $this->storage->remove();
    }

    public function testStoreMovesTheTempFileIntoAShardedPath(): void
    {
        [$source, $hash] = $this->stagedBlob('blob-contents');

        $this->store->store($source, $hash);

        self::assertFileDoesNotExist($source);
        $stored = $this->store->retrievePath($hash);
        self::assertNotNull($stored);
        self::assertSame($this->storage->getPath().'/'.substr($hash->toHex(), 0, 2).'/'.$hash->toHex(), $stored);
        self::assertSame('blob-contents', (string) file_get_contents($stored));
    }

    public function testRetrievePathReturnsNullForAnUnknownBlob(): void
    {
        $hash = BlobHash::tryFromHex(hash('sha256', 'absent'));
        self::assertNotNull($hash);

        self::assertNull($this->store->retrievePath($hash));
    }

    public function testDeleteRemovesAStoredBlob(): void
    {
        [$source, $hash] = $this->stagedBlob('to-be-deleted');
        $this->store->store($source, $hash);

        self::assertTrue($this->store->delete($hash));
        self::assertNull($this->store->retrievePath($hash));
        self::assertFalse($this->store->delete($hash));
    }

    public function testDiscardRemovesTheTempFile(): void
    {
        [$source] = $this->stagedBlob('discard-me');

        $this->store->discard($source);

        self::assertFileDoesNotExist($source);
    }

    /**
     * @return array{string, BlobHash}
     */
    private function stagedBlob(string $contents): array
    {
        $source = $this->storage->path('staged-'.bin2hex(random_bytes(6)));
        file_put_contents($source, $contents);

        $hash = BlobHash::tryFromHex(hash('sha256', $contents));
        self::assertNotNull($hash);

        return [$source, $hash];
    }
}
