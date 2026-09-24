<?php

declare(strict_types=1);

namespace Innis\Hubstr\Blossom\Tests\Acceptance\BudCompliance;

use Innis\Hubstr\Blossom\Tests\Support\BlossomTestServer;
use Innis\Hubstr\Blossom\Tests\Support\GdImageFixture;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;

final class Bud08Test extends TestCase
{
    private static BlossomTestServer $server;

    public static function setUpBeforeClass(): void
    {
        self::$server = BlossomTestServer::boot();
    }

    public static function tearDownAfterClass(): void
    {
        self::$server->stop();
    }

    private static int $pngSeed = 0;

    #[TestDox('BUD-08 — The upload response descriptor carries nip94 file metadata for a media blob')]
    public function testUploadResponseDescriptorCarriesNip94(): void
    {
        $content = $this->pngBytes(8, 8);
        $sha256 = hash('sha256', $content);

        $response = self::$server->request('PUT', '/upload', [
            'Authorization' => self::$server->authHeader('upload', $sha256),
            'Content-Type' => 'image/png',
        ], $content);

        self::assertSame(200, $response->status());
        $descriptor = json_decode($response->body(), true);
        self::assertIsArray($descriptor);

        $tags = $this->nip94Map($descriptor['nip94'] ?? null);
        self::assertSame($descriptor['url'], $tags['url'] ?? null);
        self::assertSame('image/png', $tags['m'] ?? null);
        self::assertSame($sha256, $tags['x'] ?? null);
        self::assertSame((string) strlen($content), $tags['size'] ?? null);
        self::assertSame('8x8', $tags['dim'] ?? null);
        self::assertNotSame('', $tags['blurhash'] ?? '');
        self::assertArrayNotHasKey('ox', $tags);
    }

    #[TestDox('BUD-08 — nip94 metadata round-trips through the index and into the list response')]
    public function testListEntryCarriesNip94(): void
    {
        $content = $this->pngBytes(8, 8);
        $sha256 = hash('sha256', $content);

        $upload = self::$server->request('PUT', '/upload', [
            'Authorization' => self::$server->authHeader('upload', $sha256),
            'Content-Type' => 'image/png',
        ], $content);
        self::assertSame(200, $upload->status());

        $tags = $this->listEntryNip94($sha256);
        self::assertSame('8x8', $tags['dim'] ?? null);
        self::assertNotSame('', $tags['blurhash'] ?? '');
    }

    #[TestDox('BUD-08 — The optimised /media descriptor carries nip94 file metadata')]
    public function testMediaResponseCarriesNip94(): void
    {
        $content = $this->pngBytes(16, 16);

        $response = self::$server->request('PUT', '/media', [
            'Authorization' => self::$server->authHeader('media', hash('sha256', $content)),
            'Content-Type' => 'image/png',
        ], $content);

        self::assertSame(200, $response->status());
        $descriptor = json_decode($response->body(), true);
        self::assertIsArray($descriptor);

        $tags = $this->nip94Map($descriptor['nip94'] ?? null);
        self::assertSame('16x16', $tags['dim'] ?? null);
        self::assertNotSame('', $tags['blurhash'] ?? '');
    }

    #[TestDox('BUD-08 — A non-media blob carries no nip94 metadata')]
    public function testNonMediaBlobHasNoNip94(): void
    {
        $sha256 = self::$server->seed('not an image, just bytes '.uniqid(), 'text/plain');

        $entry = $this->listEntry($sha256);
        self::assertArrayNotHasKey('nip94', $entry);
    }

    /**
     * @return array<string, mixed>
     */
    private function listEntryNip94(string $sha256): array
    {
        return $this->nip94Map($this->listEntry($sha256)['nip94'] ?? null);
    }

    /**
     * @return array<string, mixed>
     */
    private function listEntry(string $sha256): array
    {
        $response = self::$server->request('GET', '/list/'.self::$server->ownerPubkeyHex(), [
            'Authorization' => self::$server->authHeader('list'),
        ]);
        self::assertSame(200, $response->status());

        $entries = json_decode($response->body(), true);
        self::assertIsArray($entries);

        foreach ($entries as $entry) {
            if (is_array($entry) && ($entry['sha256'] ?? null) === $sha256) {
                return $entry;
            }
        }

        self::fail(sprintf('No list entry found for blob %s', $sha256));
    }

    /**
     * @return array<string, mixed>
     */
    private function nip94Map(mixed $nip94): array
    {
        $map = [];
        if (!is_array($nip94)) {
            return $map;
        }

        foreach ($nip94 as $field) {
            if (is_array($field) && isset($field[0], $field[1]) && is_string($field[0])) {
                $map[$field[0]] = $field[1];
            }
        }

        return $map;
    }

    private function pngBytes(int $width, int $height): string
    {
        $seed = ++self::$pngSeed;

        $image = GdImageFixture::canvas($width, $height);
        imagefill($image, 0, 0, GdImageFixture::colour($image, $seed % 256, ($seed * 7) % 256, ($seed * 13) % 256));

        ob_start();
        imagepng($image);

        return (string) ob_get_clean();
    }
}
