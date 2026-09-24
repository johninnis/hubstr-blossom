<?php

declare(strict_types=1);

namespace Innis\Hubstr\Blossom\Tests\Acceptance\BudCompliance;

use Innis\Hubstr\Blossom\Tests\Support\BlossomTestServer;
use Innis\Hubstr\Blossom\Tests\Support\GdImageFixture;
use Innis\Hubstr\Blossom\Tests\Support\JsonBody;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;

final class Bud02Test extends TestCase
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

    #[TestDox('BUD-02 — PUT /upload stores the blob and returns a descriptor with url, sha256, size, type and uploaded')]
    public function testUploadStoresBlobAndReturnsDescriptor(): void
    {
        $content = $this->uniquePng();
        $sha256 = hash('sha256', $content);

        $response = self::$server->request('PUT', '/upload', [
            'Authorization' => self::$server->authHeader('upload', $sha256),
            'Content-Type' => 'image/png',
        ], $content);

        self::assertSame(200, $response->status());
        $json = JsonBody::from($response->body());
        self::assertSame($sha256, $json->string('sha256'));
        self::assertSame(strlen($content), $json->int('size'));
        self::assertSame('image/png', $json->string('type'));
        self::assertStringContainsString('.png', $json->string('url'));
    }

    #[TestDox('BUD-02 — An upload without a valid authorization event is rejected with 401')]
    public function testUploadRejectsUnauthenticated(): void
    {
        $response = self::$server->request('PUT', '/upload', ['Content-Type' => 'image/png'], 'no auth');

        self::assertSame(401, $response->status());
    }

    #[TestDox('BUD-02 — An upload of an unsupported MIME type is rejected with 415')]
    public function testUploadRejectsUnsupportedMimeType(): void
    {
        $content = 'bad type';

        $response = self::$server->request('PUT', '/upload', [
            'Authorization' => self::$server->authHeader('upload', hash('sha256', $content)),
            'Content-Type' => 'application/x-evil',
        ], $content);

        self::assertSame(415, $response->status());
    }

    #[TestDox('BUD-02 — A server MAY reject an upload whose X-SHA-256 header does not match the received bytes')]
    public function testUploadRejectsWhenXSha256Mismatches(): void
    {
        $content = $this->uniquePng();

        $response = self::$server->request('PUT', '/upload', [
            'Authorization' => self::$server->authHeader('upload'),
            'Content-Type' => 'image/png',
            'X-SHA-256' => hash('sha256', 'different content'),
        ], $content);

        self::assertSame(400, $response->status());
    }

    #[TestDox('BUD-02 — An upload larger than the advertised maximum is refused with 413 and a reason')]
    public function testUploadLargerThanTheMaximumIsRefused(): void
    {
        $maximum = (int) self::$server->request('HEAD', '/upload')->header('x-max-upload-size');
        $content = str_repeat('x', $maximum + 1);

        $response = self::$server->request('PUT', '/upload', [
            'Authorization' => self::$server->authHeader('upload', hash('sha256', $content)),
            'Content-Type' => 'image/png',
        ], $content);

        self::assertSame(413, $response->status());
        self::assertSame('application/json', $response->header('content-type'));
        self::assertStringContainsString('maximum', $response->header('x-reason') ?? '');
        self::assertSame('*', $response->header('access-control-allow-origin'));
    }

    #[TestDox('BUD-02 — The stored type reflects the server-detected content, not a misdeclared Content-Type')]
    public function testUploadNormalisesTypeToDetectedContent(): void
    {
        $content = $this->pngBytes();
        $sha256 = hash('sha256', $content);

        $response = self::$server->request('PUT', '/upload', [
            'Authorization' => self::$server->authHeader('upload', $sha256),
            'Content-Type' => 'image/jpeg',
        ], $content);

        self::assertSame(200, $response->status());
        $json = JsonBody::from($response->body());
        self::assertSame('image/png', $json->string('type'));
        self::assertStringEndsWith('.png', $json->string('url'));
    }

    #[TestDox('BUD-02 — An upload whose detected type is unsupported is rejected even when the declared type is allowed')]
    public function testUploadRejectsContentTypeThatLiesAboutDisallowedBytes(): void
    {
        $content = 'this is plain text pretending to be a png '.uniqid();
        $sha256 = hash('sha256', $content);

        $response = self::$server->request('PUT', '/upload', [
            'Authorization' => self::$server->authHeader('upload', $sha256),
            'Content-Type' => 'image/png',
        ], $content);

        self::assertSame(415, $response->status());
    }

    #[TestDox('BUD-02 — A blob stored via PUT /upload can be retrieved by its sha256')]
    public function testUploadedBlobCanBeRetrieved(): void
    {
        $content = $this->uniquePng();
        $sha256 = hash('sha256', $content);

        $upload = self::$server->request('PUT', '/upload', [
            'Authorization' => self::$server->authHeader('upload', $sha256),
            'Content-Type' => 'image/png',
        ], $content);
        self::assertSame(200, $upload->status());

        $get = self::$server->request('GET', '/'.$sha256);
        self::assertSame(200, $get->status());
        self::assertSame($content, $get->body());
    }

    private function pngBytes(int $seed = 0): string
    {
        $image = GdImageFixture::canvas(8, 8);
        imagefill($image, 0, 0, GdImageFixture::colour($image, $seed % 256, ($seed * 7) % 256, ($seed * 13) % 256));

        ob_start();
        imagepng($image);

        return (string) ob_get_clean();
    }

    private function uniquePng(): string
    {
        return $this->pngBytes(++self::$pngSeed);
    }
}
