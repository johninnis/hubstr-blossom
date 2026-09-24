<?php

declare(strict_types=1);

namespace Innis\Hubstr\Blossom\Tests\Acceptance\BudCompliance;

use Innis\Hubstr\Blossom\Tests\Support\BlossomTestServer;
use Innis\Hubstr\Blossom\Tests\Support\GdImageFixture;
use Innis\Hubstr\Blossom\Tests\Support\JsonBody;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;

final class Bud04Test extends TestCase
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

    #[TestDox('BUD-04 — PUT /mirror fetches the remote blob and stores it under the authenticated tenant')]
    public function testMirrorFetchesAndStoresRemoteBlob(): void
    {
        $content = $this->createPng();
        $sha256 = hash('sha256', $content);

        $published = self::$server->request('PUT', '/upload', [
            'Authorization' => self::$server->authHeaderFor(self::$server->secondTenant(), 'upload', $sha256),
            'Content-Type' => 'image/png',
        ], $content);
        self::assertSame(200, $published->status());

        $mirror = self::$server->request('PUT', '/mirror', [
            'Authorization' => self::$server->authHeader('upload', $sha256),
            'Content-Type' => 'application/json',
        ], (string) json_encode(['url' => self::$server->baseUrl().'/'.$sha256]));

        self::assertSame(200, $mirror->status());
        $descriptor = JsonBody::from($mirror->body());
        self::assertSame($sha256, $descriptor->string('sha256'));
        self::assertSame(strlen($content), $descriptor->int('size'));
        self::assertSame('image/png', $descriptor->string('type'));

        $list = self::$server->request('GET', '/list/'.self::$server->ownerPubkeyHex(), [
            'Authorization' => self::$server->authHeader('list'),
        ]);
        self::assertSame(200, $list->status());
        self::assertContains($sha256, array_column((array) json_decode($list->body(), true), 'sha256'));
    }

    #[TestDox('BUD-04 — PUT /mirror without a valid authorization event is rejected with 401')]
    public function testMirrorRejectsUnauthenticated(): void
    {
        $response = self::$server->request('PUT', '/mirror', [
            'Content-Type' => 'application/json',
        ], (string) json_encode(['url' => self::$server->baseUrl().'/'.str_repeat('ab', 32)]));

        self::assertSame(401, $response->status());
    }

    #[TestDox('BUD-04 — PUT /mirror without a usable url field is rejected with 400')]
    public function testMirrorRejectsMissingUrl(): void
    {
        $response = self::$server->request('PUT', '/mirror', [
            'Authorization' => self::$server->authHeader('upload'),
            'Content-Type' => 'application/json',
        ], (string) json_encode(['not-a-url' => true]));

        self::assertSame(400, $response->status());
    }

    #[TestDox('BUD-04 — PUT /mirror of an unreachable source responds with 502')]
    public function testMirrorOfUnreachableSourceFails(): void
    {
        $response = self::$server->request('PUT', '/mirror', [
            'Authorization' => self::$server->authHeader('upload'),
            'Content-Type' => 'application/json',
        ], (string) json_encode(['url' => 'http://127.0.0.1:1/missing']));

        self::assertSame(502, $response->status());
    }

    private function createPng(): string
    {
        $image = GdImageFixture::canvas(24, 24);
        imagefilledrectangle($image, 0, 0, 23, 23, GdImageFixture::colour($image, 40, 90, 210));

        ob_start();
        imagepng($image);

        return (string) ob_get_clean();
    }
}
