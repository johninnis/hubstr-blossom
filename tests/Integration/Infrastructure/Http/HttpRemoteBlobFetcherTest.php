<?php

declare(strict_types=1);

namespace Innis\Hubstr\Blossom\Tests\Integration\Infrastructure\Http;

use Innis\Hubstr\Blossom\Domain\Exception\RemoteBlobFetchException;
use Innis\Hubstr\Blossom\Infrastructure\Filesystem\TempFileFactory;
use Innis\Hubstr\Blossom\Infrastructure\Http\BlobStager;
use Innis\Hubstr\Blossom\Infrastructure\Http\HttpRemoteBlobFetcher;
use Innis\Hubstr\Blossom\Infrastructure\Network\PrivateAddressGuard;
use Innis\Hubstr\Blossom\Tests\Support\BackgroundProcess;
use Innis\Hubstr\Blossom\Tests\Support\FreePort;
use Innis\Hubstr\Blossom\Tests\Support\TemporaryDirectory;
use Innis\Nostr\Blossom\Domain\ValueObject\HttpUrl;
use PHPUnit\Framework\TestCase;

final class HttpRemoteBlobFetcherTest extends TestCase
{
    private const string PAYLOAD = 'redirected-blob-bytes';
    private const int MAX_BYTES = 1048576;

    private static int $port;
    private static TemporaryDirectory $directory;
    private static BackgroundProcess $server;

    public static function setUpBeforeClass(): void
    {
        self::$port = FreePort::reserve();
        self::$directory = TemporaryDirectory::create();

        $router = self::$directory->path('router.php');
        file_put_contents($router, self::routerScript());

        self::$server = BackgroundProcess::start([PHP_BINARY, '-S', '127.0.0.1:'.self::$port, $router], self::$directory);
        self::$server->awaitListening(self::$port);
    }

    public static function tearDownAfterClass(): void
    {
        self::$server->stop();
        self::$directory->remove();
    }

    public function testFollowsAnAbsoluteRedirectToTheBlobBytes(): void
    {
        $blob = $this->fetcher()->fetch($this->url('/redirect-absolute'));

        self::assertSame(self::PAYLOAD, (string) file_get_contents($blob->getPath()));
        self::assertSame('image/png', (string) $blob->getMimeType());
    }

    public function testFollowsARelativeRedirectToTheBlobBytes(): void
    {
        $blob = $this->fetcher()->fetch($this->url('/redirect-relative'));

        self::assertSame(self::PAYLOAD, (string) file_get_contents($blob->getPath()));
        self::assertSame('image/png', (string) $blob->getMimeType());
    }

    public function testRejectsRedirectChainsThatExceedTheLimit(): void
    {
        $this->expectException(RemoteBlobFetchException::class);
        $this->expectExceptionMessage('Too many redirects');

        $this->fetcher()->fetch($this->url('/redirect-loop'));
    }

    public function testRejectsRedirectToUnsupportedScheme(): void
    {
        $this->expectException(RemoteBlobFetchException::class);
        $this->expectExceptionMessage('unsupported location');

        $this->fetcher()->fetch($this->url('/redirect-scheme'));
    }

    public function testRefusesARemoteBlobLargerThanTheMaximum(): void
    {
        $this->expectException(RemoteBlobFetchException::class);
        $this->expectExceptionMessage('exceeds the maximum size');

        $this->fetcher()->fetch($this->url('/oversize'));
    }

    public function testRefusesToFetchAPrivateLiteralAddress(): void
    {
        $this->expectException(RemoteBlobFetchException::class);
        $this->expectExceptionMessage('private or reserved address');

        new HttpRemoteBlobFetcher(self::MAX_BYTES, new PrivateAddressGuard(), $this->stager())->fetch($this->url('/payload'));
    }

    private function fetcher(): HttpRemoteBlobFetcher
    {
        return new HttpRemoteBlobFetcher(self::MAX_BYTES, new PrivateAddressGuard(true), $this->stager());
    }

    private function stager(): BlobStager
    {
        return new BlobStager(new TempFileFactory(self::$directory->getPath()), self::MAX_BYTES);
    }

    private function url(string $path): HttpUrl
    {
        $url = HttpUrl::tryFromString('http://127.0.0.1:'.self::$port.$path);
        self::assertNotNull($url);

        return $url;
    }

    private static function routerScript(): string
    {
        $payload = var_export(self::PAYLOAD, true);
        $oversize = self::MAX_BYTES + 1;

        return <<<PHP
            <?php

            \$path = parse_url(\$_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);

            switch (\$path) {
                case '/redirect-absolute':
                    header('Location: http://127.0.0.1:'.\$_SERVER['SERVER_PORT'].'/payload', true, 302);

                    return;
                case '/redirect-relative':
                    header('Location: /payload', true, 302);

                    return;
                case '/redirect-loop':
                    header('Location: /redirect-loop', true, 302);

                    return;
                case '/redirect-scheme':
                    header('Location: ftp://127.0.0.1/payload', true, 302);

                    return;
                case '/payload':
                    header('Content-Type: image/png');
                    echo {$payload};

                    return;
                case '/oversize':
                    header('Content-Type: image/png');
                    header('Content-Length: {$oversize}');
                    echo str_repeat('x', {$oversize});

                    return;
                default:
                    http_response_code(404);

                    return;
            }
            PHP;
    }
}
