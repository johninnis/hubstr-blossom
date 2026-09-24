<?php

declare(strict_types=1);

namespace Innis\Hubstr\Blossom\Tests\Support;

use Innis\Hubstr\Blossom\Infrastructure\Filesystem\FilesystemBlobStore;
use Innis\Hubstr\Blossom\Infrastructure\Filesystem\TempFileFactory;
use Innis\Hubstr\Blossom\Infrastructure\Persistence\SqliteBlobIndex;
use Innis\Hubstr\Core\Infrastructure\Persistence\SqliteDatabase;
use Innis\Nostr\Blossom\Domain\ValueObject\BlobDescriptor;
use Innis\Nostr\Blossom\Domain\ValueObject\BlobHash;
use Innis\Nostr\Blossom\Domain\ValueObject\MimeType;
use Innis\Nostr\Core\Domain\ValueObject\Identity\PublicKey;
use Innis\Nostr\Core\Domain\ValueObject\Timestamp;
use RuntimeException;

final readonly class BlobSeeder
{
    private const string STAGING_PREFIX = 'blossom_seed_';

    public function __construct(private BlossomDaemon $daemon)
    {
    }

    public function seed(string $content, string $type, PublicKey $owner): string
    {
        $hash = BlobHash::tryFromHex(hash('sha256', $content))
            ?? throw new RuntimeException('A SHA-256 digest is always a valid blob hash');
        $mimeType = MimeType::fromString($type);

        $staged = new TempFileFactory($this->daemon->storagePath().'/tmp')->create(self::STAGING_PREFIX);
        file_put_contents($staged, $content);
        new FilesystemBlobStore($this->daemon->storagePath())->store($staged, $hash);

        $index = new SqliteBlobIndex(SqliteDatabase::atPath($this->daemon->databasePath())->connect());
        $index->save($owner, new BlobDescriptor(
            url: $this->daemon->identity()->buildBlobUrl($hash, $mimeType),
            sha256: $hash,
            size: strlen($content),
            type: $mimeType,
            uploaded: Timestamp::now(),
        ));

        return $hash->toHex();
    }
}
