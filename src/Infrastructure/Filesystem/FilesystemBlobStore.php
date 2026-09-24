<?php

declare(strict_types=1);

namespace Innis\Hubstr\Blossom\Infrastructure\Filesystem;

use Innis\Hubstr\Blossom\Domain\Exception\BlobStorageException;
use Innis\Hubstr\Core\Infrastructure\Filesystem\Directory;
use Innis\Nostr\Blossom\Application\Port\BlobStoreInterface;
use Innis\Nostr\Blossom\Domain\ValueObject\BlobHash;
use Override;

final readonly class FilesystemBlobStore implements BlobStoreInterface
{
    public function __construct(private string $storagePath)
    {
    }

    #[Override]
    public function store(string $tempPath, BlobHash $hash): void
    {
        Directory::atPath($this->directoryFor($hash))->ensure();

        $targetPath = $this->pathFor($hash);
        if (!rename($tempPath, $targetPath)) {
            throw BlobStorageException::failedToMoveBlob($targetPath);
        }
    }

    #[Override]
    public function discard(string $tempPath): void
    {
        if (is_file($tempPath)) {
            unlink($tempPath);
        }
    }

    #[Override]
    public function retrievePath(BlobHash $hash): ?string
    {
        $path = $this->pathFor($hash);

        return is_file($path) ? $path : null;
    }

    #[Override]
    public function delete(BlobHash $hash): bool
    {
        $path = $this->pathFor($hash);
        if (!is_file($path)) {
            return false;
        }

        return unlink($path);
    }

    private function pathFor(BlobHash $hash): string
    {
        return sprintf('%s/%s/%s', $this->storagePath, $this->shard($hash), $hash->toHex());
    }

    private function directoryFor(BlobHash $hash): string
    {
        return sprintf('%s/%s', $this->storagePath, $this->shard($hash));
    }

    private function shard(BlobHash $hash): string
    {
        return substr($hash->toHex(), 0, 2);
    }
}
