<?php

declare(strict_types=1);

namespace Innis\Hubstr\Blossom\Infrastructure\Persistence;

use Innis\Nostr\Blossom\Application\Port\BlobIndexInterface;
use Innis\Nostr\Blossom\Domain\Collection\BlobDescriptorCollection;
use Innis\Nostr\Blossom\Domain\ValueObject\BlobDescriptor;
use Innis\Nostr\Blossom\Domain\ValueObject\BlobHash;
use Innis\Nostr\Blossom\Domain\ValueObject\ListQuery;
use Innis\Nostr\Core\Domain\ValueObject\Identity\PublicKey;
use Override;

final class CachingBlobIndex implements BlobIndexInterface
{
    private const int MAX_ENTRIES = 4096;

    /** @var array<string, BlobDescriptor> */
    private array $descriptors = [];

    public function __construct(private readonly BlobIndexInterface $index)
    {
    }

    #[Override]
    public function save(PublicKey $tenant, BlobDescriptor $descriptor): void
    {
        $this->index->save($tenant, $descriptor);
        $this->remember($descriptor->getSha256()->toHex(), $descriptor);
    }

    #[Override]
    public function findByHash(BlobHash $hash): ?BlobDescriptor
    {
        $key = $hash->toHex();
        $cached = $this->descriptors[$key] ?? null;

        if ($cached instanceof BlobDescriptor) {
            unset($this->descriptors[$key]);
            $this->descriptors[$key] = $cached;

            return $cached;
        }

        $descriptor = $this->index->findByHash($hash);
        if ($descriptor instanceof BlobDescriptor) {
            $this->remember($key, $descriptor);
        }

        return $descriptor;
    }

    #[Override]
    public function ownsBlob(PublicKey $tenant, BlobHash $hash): bool
    {
        return $this->index->ownsBlob($tenant, $hash);
    }

    #[Override]
    public function list(PublicKey $tenant, ListQuery $query): BlobDescriptorCollection
    {
        return $this->index->list($tenant, $query);
    }

    #[Override]
    public function deleteForTenant(PublicKey $tenant, BlobHash $hash): bool
    {
        unset($this->descriptors[$hash->toHex()]);

        return $this->index->deleteForTenant($tenant, $hash);
    }

    private function remember(string $key, BlobDescriptor $descriptor): void
    {
        unset($this->descriptors[$key]);

        if (count($this->descriptors) >= self::MAX_ENTRIES) {
            unset($this->descriptors[array_key_first($this->descriptors)]);
        }

        $this->descriptors[$key] = $descriptor;
    }
}
