<?php

declare(strict_types=1);

namespace Innis\Hubstr\Blossom\Tests\Acceptance\BudCompliance;

use Innis\Hubstr\Blossom\Tests\Support\BlossomTestServer;
use Innis\Hubstr\Blossom\Tests\Support\GdImageFixture;
use Innis\Hubstr\Blossom\Tests\Support\JsonBody;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;

final class Bud05Test extends TestCase
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

    #[TestDox('BUD-05 — PUT /media optimises the media and responds with a Blob Descriptor')]
    public function testMediaOptimisesAndStoresImageViaWorkerPool(): void
    {
        $png = $this->createPng();

        $response = self::$server->request('PUT', '/media', [
            'Authorization' => self::$server->authHeader('media', hash('sha256', $png)),
            'Content-Type' => 'image/png',
        ], $png);

        self::assertSame(200, $response->status());
        $json = JsonBody::from($response->body());
        $sha256 = $json->string('sha256');
        self::assertSame('image/png', $json->string('type'));
        self::assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $sha256);

        $get = self::$server->request('GET', '/'.$sha256);
        self::assertSame(200, $get->status());
        self::assertSame($sha256, hash('sha256', $get->body()));
        self::assertSame('"'.$sha256.'"', $get->header('etag'));
    }

    #[TestDox('BUD-05 — PUT /media returns 415 when the media type is not supported')]
    public function testMediaRejectsUnsupportedType(): void
    {
        $response = self::$server->request('PUT', '/media', [
            'Authorization' => self::$server->authHeader('media'),
            'Content-Type' => 'application/x-evil',
        ], 'not an image');

        self::assertSame(415, $response->status());
    }

    #[TestDox('BUD-05 — PUT /media without a valid authorization event is rejected with 401')]
    public function testMediaRejectsUnauthenticated(): void
    {
        $response = self::$server->request('PUT', '/media', ['Content-Type' => 'image/png'], $this->createPng());

        self::assertSame(401, $response->status());
    }

    private function createPng(): string
    {
        $image = GdImageFixture::canvas(16, 16);
        imagefilledrectangle($image, 0, 0, 15, 15, GdImageFixture::colour($image, 120, 200, 80));

        ob_start();
        imagepng($image);

        return (string) ob_get_clean();
    }
}
