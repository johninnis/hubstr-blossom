<?php

declare(strict_types=1);

namespace Innis\Hubstr\Blossom\Tests\Support;

use Innis\Nostr\Blossom\Domain\ValueObject\HttpUrl;
use Innis\Nostr\Blossom\Domain\ValueObject\ServerIdentity;
use RuntimeException;

final readonly class BlossomDaemon
{
    private const string DATABASE_FILE = 'hubstr-blossom.sqlite';

    private function __construct(
        private BackgroundProcess $process,
        private ServerIdentity $identity,
        private TemporaryDirectory $directory,
    ) {
    }

    /**
     * @param list<string> $tenantPubkeyHexes
     */
    public static function start(array $tenantPubkeyHexes): self
    {
        $port = FreePort::reserve();
        $directory = TemporaryDirectory::create();
        $baseUrl = HttpUrl::tryFromString('http://127.0.0.1:'.$port)
            ?? throw new RuntimeException('Failed to build the daemon base URL');

        $configPath = $directory->path('config.php');
        file_put_contents($configPath, self::renderConfig($tenantPubkeyHexes, $baseUrl, $directory));

        $process = BackgroundProcess::start(
            [PHP_BINARY, dirname(__DIR__, 2).'/bin/hubstr-blossom.php'],
            $directory,
            ['HUBSTR_BLOSSOM_CONFIG' => $configPath],
        );
        $process->awaitListening($port);

        return new self($process, new ServerIdentity($baseUrl), $directory);
    }

    public function identity(): ServerIdentity
    {
        return $this->identity;
    }

    public function baseUrl(): string
    {
        return (string) $this->identity->getBaseUrl();
    }

    public function storagePath(): string
    {
        return $this->directory->getPath();
    }

    public function databasePath(): string
    {
        return $this->directory->path(self::DATABASE_FILE);
    }

    public function stop(): void
    {
        $this->process->stop();
        $this->directory->remove();
    }

    /**
     * @param list<string> $tenantPubkeyHexes
     */
    private static function renderConfig(array $tenantPubkeyHexes, HttpUrl $baseUrl, TemporaryDirectory $directory): string
    {
        $values = [
            'tenant_pubkeys' => $tenantPubkeyHexes,
            'host' => $baseUrl->getHost(),
            'port' => $baseUrl->getPort(),
            'base_url' => (string) $baseUrl,
            'storage_path' => $directory->getPath(),
            'database_path' => $directory->path(self::DATABASE_FILE),
            'max_upload_bytes' => 1048576,
            'allowed_types' => ['image/png', 'image/jpeg', 'image/gif', 'image/webp', 'video/mp4', 'audio/mpeg'],
            'allow_private_mirror_hosts' => true,
            'trusted_proxies' => ['127.0.0.1'],
            'log_level' => 'error',
        ];

        return '<?php return '.var_export($values, true).';';
    }
}
