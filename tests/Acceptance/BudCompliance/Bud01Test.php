<?php

declare(strict_types=1);

namespace Innis\Hubstr\Blossom\Tests\Acceptance\BudCompliance;

use Innis\Hubstr\Blossom\Tests\Support\BlossomTestServer;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;

final class Bud01Test extends TestCase
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

    private string $blobContent = 'test blob content for BUD-01';

    #[TestDox('BUD-01 — GET /<sha256> MUST return the blob contents with the appropriate Content-Type header')]
    public function testGetReturnsBlobContentsWithContentType(): void
    {
        $sha256 = self::$server->seed($this->blobContent, 'image/png');

        $response = self::$server->request('GET', '/'.$sha256.'.png');

        self::assertSame(200, $response->status());
        self::assertSame($this->blobContent, $response->body());
        self::assertSame('image/png', $response->header('content-type'));
        self::assertSame((string) strlen($this->blobContent), $response->header('content-length'));
        self::assertSame('bytes', $response->header('accept-ranges'));
        self::assertSame('"'.$sha256.'"', $response->header('etag'));
        self::assertStringContainsString('immutable', $response->header('cache-control') ?? '');
    }

    #[TestDox('BUD-01 — GET with a matching If-None-Match returns 304 Not Modified without a body')]
    public function testConditionalGetReturnsNotModified(): void
    {
        $sha256 = self::$server->seed($this->blobContent, 'image/png');

        $response = self::$server->request('GET', '/'.$sha256, ['If-None-Match' => '"'.$sha256.'"']);

        self::assertSame(304, $response->status());
        self::assertSame('', $response->body());
        self::assertSame('"'.$sha256.'"', $response->header('etag'));
    }

    #[TestDox('BUD-01 — GET /<sha256> honours a byte Range request with a 206 partial response')]
    public function testGetServesByteRange(): void
    {
        $sha256 = self::$server->seed($this->blobContent, 'image/png');

        $response = self::$server->request('GET', '/'.$sha256, ['Range' => 'bytes=0-3']);

        self::assertSame(206, $response->status());
        self::assertSame(substr($this->blobContent, 0, 4), $response->body());
        self::assertSame('4', $response->header('content-length'));
        self::assertSame(
            'bytes 0-3/'.strlen($this->blobContent),
            $response->header('content-range'),
        );
    }

    #[TestDox('BUD-01 — GET /<sha256> rejects an unsatisfiable Range with a 416 response')]
    public function testGetRejectsUnsatisfiableRange(): void
    {
        $sha256 = self::$server->seed($this->blobContent, 'image/png');

        $response = self::$server->request('GET', '/'.$sha256, ['Range' => 'bytes=999999-']);

        self::assertSame(416, $response->status());
        self::assertSame('bytes */'.strlen($this->blobContent), $response->header('content-range'));
    }

    #[TestDox('BUD-01 — GET /<sha256> MUST accept an optional file extension and also serve the bare hash')]
    public function testGetReturnsBlobWithoutExtension(): void
    {
        $sha256 = self::$server->seed($this->blobContent, 'image/png');

        $response = self::$server->request('GET', '/'.$sha256);

        self::assertSame(200, $response->status());
        self::assertSame('image/png', $response->header('content-type'));
    }

    #[TestDox('BUD-01 — A GET for a blob that does not exist returns a 404 error response')]
    public function testGetReturns404ForMissingBlob(): void
    {
        $response = self::$server->request('GET', '/'.hash('sha256', 'does not exist'));

        self::assertSame(404, $response->status());
        self::assertNotNull($response->header('x-reason'));
    }

    #[TestDox('BUD-01 — HEAD /<sha256> MUST return the same Content-Type and Content-Length headers as GET, without a body')]
    public function testHeadReturnsMetadataHeaders(): void
    {
        $sha256 = self::$server->seed($this->blobContent, 'image/png');

        $response = self::$server->request('HEAD', '/'.$sha256.'.png');

        self::assertSame(200, $response->status());
        self::assertSame('image/png', $response->header('content-type'));
        self::assertSame((string) strlen($this->blobContent), $response->header('content-length'));
        self::assertSame('bytes', $response->header('accept-ranges'));
    }

    #[TestDox('BUD-01 — A HEAD for a blob that does not exist returns a 404 status')]
    public function testHeadReturns404ForMissingBlob(): void
    {
        $response = self::$server->request('HEAD', '/'.hash('sha256', 'not here'));

        self::assertSame(404, $response->status());
    }

    #[TestDox('BUD-01 — Servers MUST set the Access-Control-Allow-Origin: * header on all responses')]
    public function testCorsHeadersOnGetResponse(): void
    {
        $sha256 = self::$server->seed($this->blobContent, 'image/png');

        $response = self::$server->request('GET', '/'.$sha256);

        self::assertSame('*', $response->header('access-control-allow-origin'));
    }

    #[TestDox('BUD-01 — A request the HTTP driver rejects before it reaches a route still carries the CORS headers')]
    public function testCorsHeadersOnAnErrorTheDriverAnswersItself(): void
    {
        $response = self::$server->probe("GET\r\n\r\n");

        self::assertSame(400, $response->status());
        self::assertSame('*', $response->header('access-control-allow-origin'));
    }

    #[TestDox('BUD-01 — Preflight responses set Access-Control-Allow-Headers and Access-Control-Allow-Methods')]
    public function testOptionsPreflightReturns204(): void
    {
        $response = self::$server->request('OPTIONS', '/upload');

        self::assertSame(204, $response->status());
        self::assertSame('*', $response->header('access-control-allow-origin'));
        self::assertSame('DELETE, GET, HEAD, OPTIONS, PUT', $response->header('access-control-allow-methods'));
        self::assertSame('Authorization, *', $response->header('access-control-allow-headers'));
    }

    #[TestDox('BUD-01 — Every preflight advertises GET, HEAD, PUT and DELETE at minimum')]
    public function testOptionsOnUploadAdvertisesTheRequiredMethods(): void
    {
        $methods = self::$server->request('OPTIONS', '/upload')->header('access-control-allow-methods');

        self::assertNotNull($methods);
        foreach (['GET', 'HEAD', 'PUT', 'DELETE'] as $required) {
            self::assertContains($required, array_map(trim(...), explode(',', $methods)));
        }
    }

    #[TestDox('BUD-01 — The blob path preflight advertises every required method')]
    public function testOptionsOnBlobPathAdvertisesTheRequiredMethods(): void
    {
        $response = self::$server->request('OPTIONS', '/'.hash('sha256', 'some blob'));

        self::assertSame(204, $response->status());
        self::assertSame('DELETE, GET, HEAD, OPTIONS, PUT', $response->header('access-control-allow-methods'));
    }

    #[TestDox('BUD-01 — Error responses (status >= 400) MAY include a human-readable X-Reason header')]
    public function testErrorResponseIncludesXReasonHeader(): void
    {
        $response = self::$server->request('GET', '/'.hash('sha256', 'nowhere'));

        self::assertSame(404, $response->status());
        $xReason = $response->header('x-reason');
        self::assertNotNull($xReason);
        self::assertStringContainsString('not found', strtolower($xReason));
    }
}
