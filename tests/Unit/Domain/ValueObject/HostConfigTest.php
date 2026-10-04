<?php

declare(strict_types=1);

namespace Innis\Hubstr\Blossom\Tests\Unit\Domain\ValueObject;

use Innis\Hubstr\Blossom\Domain\ValueObject\HostConfig;
use Innis\Hubstr\Core\Domain\ValueObject\ConfigValues;
use Innis\Nostr\Core\Domain\ValueObject\Identity\PublicKey;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class HostConfigTest extends TestCase
{
    public function testWorkerPoolLimitDefaultsToAuto(): void
    {
        $config = HostConfig::fromValues(ConfigValues::fromArray($this->base()));

        self::assertSame(0, $config->getWorkerPoolLimit());
    }

    public function testWorkerPoolLimitIsParsed(): void
    {
        $config = HostConfig::fromValues(ConfigValues::fromArray([...$this->base(), 'worker_pool_limit' => 3]));

        self::assertSame(3, $config->getWorkerPoolLimit());
    }

    public function testNegativeWorkerPoolLimitIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        HostConfig::fromValues(ConfigValues::fromArray([...$this->base(), 'worker_pool_limit' => -1]));
    }

    public function testALogPathIsRejectedBecauseTheLogGoesToStandardOutputOnly(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown config key: log_path');

        HostConfig::fromValues(ConfigValues::fromArray([...$this->base(), 'log_path' => '/var/log/blossom.log']));
    }

    public function testEmptyDatabasePathIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        HostConfig::fromValues(ConfigValues::fromArray([...$this->base(), 'database_path' => '']));
    }

    public function testNonPositiveMaxUploadBytesIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        HostConfig::fromValues(ConfigValues::fromArray([...$this->base(), 'max_upload_bytes' => 0]));
    }

    public function testMissingMaxUploadBytesIsRejected(): void
    {
        $this->expectExceptionMessage('max_upload_bytes is required');

        HostConfig::fromValues(ConfigValues::fromArray($this->without('max_upload_bytes')));
    }

    public function testMissingTenantPubkeysIsRejected(): void
    {
        $this->expectExceptionMessage('tenant_pubkeys must list at least one public key, as 64 hex characters or an npub');

        HostConfig::fromValues(ConfigValues::fromArray($this->without('tenant_pubkeys')));
    }

    public function testEmptyTenantPubkeysAreRejected(): void
    {
        $this->expectExceptionMessage('tenant_pubkeys must list at least one public key, as 64 hex characters or an npub');

        HostConfig::fromValues(ConfigValues::fromArray([...$this->base(), 'tenant_pubkeys' => []]));
    }

    public function testATenantPubkeyMayBeGivenAsAnNpub(): void
    {
        $npub = PublicKey::tryFromHex(str_repeat('a', 64))?->toBech32();

        $config = HostConfig::fromValues(ConfigValues::fromArray([...$this->base(), 'tenant_pubkeys' => [$npub]]));

        self::assertSame(str_repeat('a', 64), $config->getServerConfig()->getTenantPubkeys()->toArray()[0]->toHex());
    }

    public function testATenantPubkeyThatIsNeitherHexNorAnNpubIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid tenant public key: not-a-hex-key');

        HostConfig::fromValues(ConfigValues::fromArray([...$this->base(), 'tenant_pubkeys' => ['not-a-hex-key']]));
    }

    public function testAKeyItDoesNotKnowIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown config key: max_upload_byte');

        HostConfig::fromValues(ConfigValues::fromArray([...$this->base(), 'max_upload_byte' => 1024]));
    }

    public function testMaxImagePixelsDefaultsWhenAbsent(): void
    {
        $config = HostConfig::fromValues(ConfigValues::fromArray($this->base()));

        self::assertSame(50_000_000, $config->getMaxImagePixels());
    }

    public function testMaxImagePixelsIsParsed(): void
    {
        $config = HostConfig::fromValues(ConfigValues::fromArray([...$this->base(), 'max_image_pixels' => 12_000_000]));

        self::assertSame(12_000_000, $config->getMaxImagePixels());
    }

    public function testNonPositiveMaxImagePixelsIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        HostConfig::fromValues(ConfigValues::fromArray([...$this->base(), 'max_image_pixels' => 0]));
    }

    public function testAllowedTypesAreParsedThroughTheStrictMimeTypeParser(): void
    {
        $config = HostConfig::fromValues(ConfigValues::fromArray([...$this->base(), 'allowed_types' => [' Image/PNG ', 'video/mp4']]));

        self::assertSame(['image/png', 'video/mp4'], array_map(strval(...), $config->getServerConfig()->getUploadConstraints()->getAllowedMimeTypes()->toArray()));
    }

    public function testAnAllowedTypeThatIsNotAMimeTypeIsRejectedRatherThanDegradedToTheGenericType(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid MIME type in allowed_types: imagepng');

        HostConfig::fromValues(ConfigValues::fromArray([...$this->base(), 'allowed_types' => ['image/png', 'imagepng']]));
    }

    public function testEmptyAllowedTypesAreRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('allowed_types must list at least one MIME type');

        HostConfig::fromValues(ConfigValues::fromArray([...$this->base(), 'allowed_types' => []]));
    }

    public function testACommaSeparatedAllowedTypesStringIsRejectedByName(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('allowed_types must be a list of non-empty strings');

        HostConfig::fromValues(ConfigValues::fromArray([...$this->base(), 'allowed_types' => 'image/png,image/jpeg']));
    }

    public function testTenantPubkeysAreExposedThroughServerConfig(): void
    {
        $config = HostConfig::fromValues(ConfigValues::fromArray($this->base()));

        self::assertCount(1, $config->getServerConfig()->getTenantPubkeys()->toArray());
    }

    /**
     * @return array<string, mixed>
     */
    private function without(string $key): array
    {
        return array_diff_key($this->base(), [$key => null]);
    }

    /**
     * @return array<string, mixed>
     */
    private function base(): array
    {
        return [
            'tenant_pubkeys' => [str_repeat('a', 64)],
            'base_url' => 'https://blossom.example.com',
            'port' => 8081,
            'storage_path' => sys_get_temp_dir().'/blossom-hostconfig-test',
            'database_path' => sys_get_temp_dir().'/blossom-hostconfig-test/hubstr-blossom.sqlite',
            'max_upload_bytes' => 1024,
            'allowed_types' => ['image/png'],
        ];
    }
}
