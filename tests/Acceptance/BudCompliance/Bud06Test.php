<?php

declare(strict_types=1);

namespace Innis\Hubstr\Blossom\Tests\Acceptance\BudCompliance;

use Innis\Hubstr\Blossom\Tests\Support\BlossomTestServer;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;

final class Bud06Test extends TestCase
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

    #[TestDox('BUD-06 — HEAD /upload advertises the server maximum upload size')]
    public function testHeadUploadReturnsMaxSizeHeader(): void
    {
        $response = self::$server->request('HEAD', '/upload');

        self::assertSame(200, $response->status());
        self::assertNotNull($response->header('x-max-upload-size'));
    }

    #[TestDox('BUD-06 — HEAD /upload advertises the accepted content types')]
    public function testHeadUploadReturnsAllowedTypesHeader(): void
    {
        $response = self::$server->request('HEAD', '/upload');

        self::assertSame(200, $response->status());
        $types = $response->header('x-content-types');
        self::assertNotNull($types);
        self::assertStringContainsString('image/png', $types);
    }

    #[TestDox('BUD-06 — HEAD /media advertises the same upload requirements')]
    public function testHeadMediaReturnsRequirements(): void
    {
        $response = self::$server->request('HEAD', '/media');

        self::assertSame(200, $response->status());
        self::assertNotNull($response->header('x-max-upload-size'));
        self::assertNotNull($response->header('x-content-types'));
    }

    #[TestDox('BUD-06 — HEAD /upload accepts a declared blob that satisfies the requirements')]
    public function testHeadUploadAcceptsValidDeclaration(): void
    {
        $hash = str_repeat('a', 64);

        $response = self::$server->request('HEAD', '/upload', [
            'Authorization' => self::$server->authHeader('upload', $hash),
            'X-SHA-256' => $hash,
            'X-Content-Length' => '1024',
            'X-Content-Type' => 'image/png',
        ]);

        self::assertSame(200, $response->status());
    }

    #[TestDox('BUD-06 — HEAD /upload rejects a declared blob larger than the maximum with 413')]
    public function testHeadUploadRejectsOversizedDeclaration(): void
    {
        $hash = str_repeat('b', 64);

        $response = self::$server->request('HEAD', '/upload', [
            'Authorization' => self::$server->authHeader('upload', $hash),
            'X-SHA-256' => $hash,
            'X-Content-Length' => '999999999999',
            'X-Content-Type' => 'image/png',
        ]);

        self::assertSame(413, $response->status());
    }

    #[TestDox('BUD-06 — HEAD /upload rejects a declared blob of an unsupported type with 415')]
    public function testHeadUploadRejectsUnsupportedType(): void
    {
        $hash = str_repeat('c', 64);

        $response = self::$server->request('HEAD', '/upload', [
            'Authorization' => self::$server->authHeader('upload', $hash),
            'X-SHA-256' => $hash,
            'X-Content-Length' => '1024',
            'X-Content-Type' => 'application/x-evil',
        ]);

        self::assertSame(415, $response->status());
    }

    #[TestDox('BUD-06 — HEAD /upload rejects a declared blob whose x tag does not bind it with 403')]
    public function testHeadUploadRejectsUnboundDeclaration(): void
    {
        $response = self::$server->request('HEAD', '/upload', [
            'Authorization' => self::$server->authHeader('upload', str_repeat('d', 64)),
            'X-SHA-256' => str_repeat('e', 64),
            'X-Content-Length' => '1024',
            'X-Content-Type' => 'image/png',
        ]);

        self::assertSame(403, $response->status());
    }

    #[TestDox('BUD-06 — HEAD /upload rejects a declared blob without authorization with 401')]
    public function testHeadUploadRejectsUnauthenticatedDeclaration(): void
    {
        $hash = str_repeat('f', 64);

        $response = self::$server->request('HEAD', '/upload', [
            'X-SHA-256' => $hash,
            'X-Content-Length' => '1024',
            'X-Content-Type' => 'image/png',
        ]);

        self::assertSame(401, $response->status());
    }

    #[TestDox('BUD-06 — HEAD /upload rejects a declaration missing X-Content-Length with 411')]
    public function testHeadUploadRequiresContentLength(): void
    {
        $hash = str_repeat('1', 64);

        $response = self::$server->request('HEAD', '/upload', [
            'Authorization' => self::$server->authHeader('upload', $hash),
            'X-SHA-256' => $hash,
            'X-Content-Type' => 'image/png',
        ]);

        self::assertSame(411, $response->status());
    }

    #[TestDox('BUD-06 — HEAD /media accepts a declared blob the optimiser can handle')]
    public function testHeadMediaAcceptsOptimisableImage(): void
    {
        $hash = str_repeat('2', 64);

        $response = self::$server->request('HEAD', '/media', [
            'Authorization' => self::$server->authHeader('media', $hash),
            'X-SHA-256' => $hash,
            'X-Content-Length' => '2048',
            'X-Content-Type' => 'image/png',
        ]);

        self::assertSame(200, $response->status());
    }

    #[TestDox('BUD-06 — HEAD /media judges by optimiser support, rejecting an allowed but non-optimisable type with 415')]
    public function testHeadMediaRejectsNonOptimisableType(): void
    {
        $hash = str_repeat('3', 64);

        $response = self::$server->request('HEAD', '/media', [
            'Authorization' => self::$server->authHeader('media', $hash),
            'X-SHA-256' => $hash,
            'X-Content-Length' => '2048',
            'X-Content-Type' => 'audio/mpeg',
        ]);

        self::assertSame(415, $response->status());
    }

    #[TestDox('BUD-06 — The /upload preflight permits every declaration header a browser client sends')]
    public function testUploadPreflightPermitsDeclarationHeaders(): void
    {
        $this->assertPreflightPermitsDeclarationHeaders('/upload');
    }

    #[TestDox('BUD-06 — The /media preflight permits every declaration header a browser client sends')]
    public function testMediaPreflightPermitsDeclarationHeaders(): void
    {
        $this->assertPreflightPermitsDeclarationHeaders('/media');
    }

    private function assertPreflightPermitsDeclarationHeaders(string $path): void
    {
        $allowed = self::$server->request('OPTIONS', $path)->header('access-control-allow-headers');

        self::assertNotNull($allowed);

        $permitted = array_map(strtolower(...), array_map(trim(...), explode(',', $allowed)));
        self::assertContains('authorization', $permitted);
        self::assertContains('*', $permitted);
    }
}
