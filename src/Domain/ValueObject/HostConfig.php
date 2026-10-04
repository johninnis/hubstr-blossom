<?php

declare(strict_types=1);

namespace Innis\Hubstr\Blossom\Domain\ValueObject;

use Innis\Hubstr\Core\Domain\ValueObject\ConfigValues;
use Innis\Hubstr\Core\Domain\ValueObject\ServiceRuntimeConfig;
use Innis\Nostr\Blossom\Domain\Collection\AllowedMimeTypes;
use Innis\Nostr\Blossom\Domain\ValueObject\HttpUrl;
use Innis\Nostr\Blossom\Domain\ValueObject\MimeType;
use Innis\Nostr\Blossom\Domain\ValueObject\ServerConfig;
use Innis\Nostr\Blossom\Domain\ValueObject\ServerIdentity;
use Innis\Nostr\Blossom\Domain\ValueObject\TenantPubkeys;
use Innis\Nostr\Blossom\Domain\ValueObject\UploadConstraints;
use Innis\Nostr\Core\Domain\ValueObject\Identity\PublicKey;
use InvalidArgumentException;

final readonly class HostConfig
{
    private const array KEYS = ['tenant_pubkeys', 'base_url', 'storage_path', 'allowed_types', 'max_upload_bytes', 'max_image_pixels', 'worker_pool_limit', 'allow_private_mirror_hosts'];
    private const int DEFAULT_MAX_IMAGE_PIXELS = 50_000_000;

    public function __construct(
        private ServiceRuntimeConfig $runtime,
        private string $storagePath,
        private bool $allowPrivateMirrorHosts,
        private int $workerPoolLimit,
        private int $maxImagePixels,
        private ServerConfig $serverConfig,
    ) {
    }

    public function getRuntime(): ServiceRuntimeConfig
    {
        return $this->runtime;
    }

    public function getStoragePath(): string
    {
        return $this->storagePath;
    }

    public function allowsPrivateMirrorHosts(): bool
    {
        return $this->allowPrivateMirrorHosts;
    }

    public function getWorkerPoolLimit(): int
    {
        return $this->workerPoolLimit;
    }

    public function getMaxImagePixels(): int
    {
        return $this->maxImagePixels;
    }

    public function getServerConfig(): ServerConfig
    {
        return $this->serverConfig;
    }

    public static function fromValues(ConfigValues $values): self
    {
        $values->rejectUnknownKeys(...self::KEYS, ...ServiceRuntimeConfig::KEYS);

        $runtime = ServiceRuntimeConfig::fromValues($values);

        $tenantPubkeys = new TenantPubkeys(self::parseTenantPubkeys($values->optionalStringList('tenant_pubkeys') ?? []));

        $baseUrlRaw = $values->string('base_url');
        $baseUrl = HttpUrl::tryFromString($baseUrlRaw)
            ?? throw new InvalidArgumentException(sprintf('Invalid base URL: %s', $baseUrlRaw));

        $storagePath = $values->string('storage_path');

        $allowedMimeTypes = self::parseMimeTypes($values->optionalStringList('allowed_types') ?? []);

        $workerPoolLimit = $values->optionalInt('worker_pool_limit') ?? 0;
        if ($workerPoolLimit < 0) {
            throw new InvalidArgumentException(sprintf('worker_pool_limit must not be negative: %d', $workerPoolLimit));
        }

        $maxImagePixels = $values->optionalInt('max_image_pixels') ?? self::DEFAULT_MAX_IMAGE_PIXELS;
        if ($maxImagePixels < 1) {
            throw new InvalidArgumentException(sprintf('max_image_pixels must be a positive integer: %d', $maxImagePixels));
        }

        $maxUploadBytes = $values->int('max_upload_bytes');
        if ($maxUploadBytes < 1) {
            throw new InvalidArgumentException(sprintf('max_upload_bytes must be a positive integer: %d', $maxUploadBytes));
        }

        $serverConfig = new ServerConfig(
            $tenantPubkeys,
            new UploadConstraints(
                $maxUploadBytes,
                new AllowedMimeTypes($allowedMimeTypes),
            ),
            new ServerIdentity($baseUrl),
        );

        return new self(
            runtime: $runtime,
            storagePath: $storagePath,
            allowPrivateMirrorHosts: $values->optionalBool('allow_private_mirror_hosts') ?? false,
            workerPoolLimit: $workerPoolLimit,
            maxImagePixels: $maxImagePixels,
            serverConfig: $serverConfig,
        );
    }

    /**
     * @param list<non-empty-string> $keys
     *
     * @return list<PublicKey>
     */
    private static function parseTenantPubkeys(array $keys): array
    {
        if ([] === $keys) {
            throw new InvalidArgumentException('tenant_pubkeys must list at least one public key, as 64 hex characters or an npub');
        }

        return array_map(
            static fn (string $key): PublicKey => PublicKey::tryFromNpubOrHex($key)
                ?? throw new InvalidArgumentException(sprintf('Invalid tenant public key: %s', $key)),
            $keys,
        );
    }

    /**
     * @param list<non-empty-string> $types
     *
     * @return list<MimeType>
     */
    private static function parseMimeTypes(array $types): array
    {
        if ([] === $types) {
            throw new InvalidArgumentException('allowed_types must list at least one MIME type');
        }

        return array_map(
            static fn (string $type): MimeType => MimeType::tryFromString($type)
                ?? throw new InvalidArgumentException(sprintf('Invalid MIME type in allowed_types: %s', $type)),
            $types,
        );
    }
}
