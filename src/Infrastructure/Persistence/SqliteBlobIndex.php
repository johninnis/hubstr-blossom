<?php

declare(strict_types=1);

namespace Innis\Hubstr\Blossom\Infrastructure\Persistence;

use Innis\Hubstr\Blossom\Domain\Exception\CorruptRowException;
use Innis\Nostr\Blossom\Application\Port\BlobIndexInterface;
use Innis\Nostr\Blossom\Domain\Collection\BlobDescriptorCollection;
use Innis\Nostr\Blossom\Domain\ValueObject\BlobDescriptor;
use Innis\Nostr\Blossom\Domain\ValueObject\BlobHash;
use Innis\Nostr\Blossom\Domain\ValueObject\HttpUrl;
use Innis\Nostr\Blossom\Domain\ValueObject\ListQuery;
use Innis\Nostr\Blossom\Domain\ValueObject\MimeType;
use Innis\Nostr\Core\Domain\ValueObject\Content\FileMetadata;
use Innis\Nostr\Core\Domain\ValueObject\Identity\PublicKey;
use Innis\Nostr\Core\Domain\ValueObject\Timestamp;
use Override;
use PDO;

final readonly class SqliteBlobIndex implements BlobIndexInterface
{
    private const string TABLE = 'blobs';

    public function __construct(private PDO $pdo)
    {
    }

    #[Override]
    public function save(PublicKey $tenant, BlobDescriptor $descriptor): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT OR REPLACE INTO blobs (tenant_pubkey, sha256, url, size, type, uploaded, dimensions, blurhash, original_hash)
             VALUES (:tenant, :sha256, :url, :size, :type, :uploaded, :dimensions, :blurhash, :original_hash)'
        );

        $nip94 = $descriptor->getNip94();
        $stmt->execute([
            ':tenant' => $tenant->toHex(),
            ':sha256' => $descriptor->getSha256()->toHex(),
            ':url' => (string) $descriptor->getUrl(),
            ':size' => $descriptor->getSize(),
            ':type' => (string) $descriptor->getType(),
            ':uploaded' => $descriptor->getUploaded()->toInt(),
            ':dimensions' => $nip94?->getDimensions(),
            ':blurhash' => $nip94?->getBlurhash(),
            ':original_hash' => $nip94?->getOriginalHash(),
        ]);
    }

    #[Override]
    public function findByHash(BlobHash $hash): ?BlobDescriptor
    {
        $stmt = $this->pdo->prepare('SELECT * FROM blobs WHERE sha256 = :sha256 LIMIT 1');
        $stmt->execute([':sha256' => $hash->toHex()]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!is_array($row)) {
            return null;
        }

        return $this->hydrateDescriptor(new StoredRow(self::TABLE, $row));
    }

    #[Override]
    public function ownsBlob(PublicKey $tenant, BlobHash $hash): bool
    {
        $stmt = $this->pdo->prepare('SELECT 1 FROM blobs WHERE tenant_pubkey = :tenant AND sha256 = :sha256 LIMIT 1');
        $stmt->execute([':tenant' => $tenant->toHex(), ':sha256' => $hash->toHex()]);

        return false !== $stmt->fetchColumn();
    }

    #[Override]
    public function list(PublicKey $tenant, ListQuery $query): BlobDescriptorCollection
    {
        $window = new TimeWindowClause('uploaded', $query);

        $stmt = $this->pdo->prepare(sprintf(
            'SELECT * FROM blobs WHERE %s ORDER BY uploaded DESC, sha256 DESC LIMIT :limit',
            implode(' AND ', ['tenant_pubkey = :tenant', ...$window->getConditions()]),
        ));
        $stmt->bindValue(':tenant', $tenant->toHex());
        foreach ($window->getParameters() as $parameter => $value) {
            $stmt->bindValue($parameter, $value, PDO::PARAM_INT);
        }
        $stmt->bindValue(':limit', $query->getLimit(), PDO::PARAM_INT);
        $stmt->execute();

        $results = [];
        while (is_array($row = $stmt->fetch(PDO::FETCH_ASSOC))) {
            $results[] = $this->hydrateDescriptor(new StoredRow(self::TABLE, $row));
        }

        return new BlobDescriptorCollection($results);
    }

    #[Override]
    public function deleteForTenant(PublicKey $tenant, BlobHash $hash): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM blobs WHERE tenant_pubkey = :tenant AND sha256 = :sha256');
        $stmt->execute([':tenant' => $tenant->toHex(), ':sha256' => $hash->toHex()]);

        return $stmt->rowCount() > 0;
    }

    private function hydrateDescriptor(StoredRow $row): BlobDescriptor
    {
        $hashHex = $row->string('sha256');
        $sha256 = BlobHash::tryFromHex($hashHex) ?? throw CorruptRowException::invalidHash(self::TABLE, $hashHex);

        $urlString = $row->string('url');
        $url = HttpUrl::tryFromString($urlString) ?? throw CorruptRowException::invalidUrl(self::TABLE, $urlString);

        $mimeType = $row->string('type');
        $size = $row->int('size');

        $dimensions = $row->optionalString('dimensions');
        $blurhash = $row->optionalString('blurhash');
        $originalHash = $row->optionalString('original_hash');

        $nip94 = null === $dimensions && null === $blurhash && null === $originalHash
            ? null
            : FileMetadata::from(
                url: $urlString,
                mimeType: $mimeType,
                hash: $hashHex,
                originalHash: $originalHash,
                size: $size,
                dimensions: $dimensions,
                blurhash: $blurhash,
            );

        return new BlobDescriptor(
            url: $url,
            sha256: $sha256,
            size: $size,
            type: MimeType::fromString($mimeType),
            uploaded: Timestamp::fromInt($row->int('uploaded')),
            nip94: $nip94,
        );
    }
}
