<?php

declare(strict_types=1);

namespace Innis\Hubstr\Blossom\Tests\Acceptance\BudCompliance;

use Innis\Hubstr\Blossom\Tests\Support\BlossomTestServer;
use Innis\Hubstr\Blossom\Tests\Support\HttpResponse;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;

final class Bud11Test extends TestCase
{
    private const string DECLARED_HASH = 'c3ab8ff13720e8ad9047dd39466b3c8974e592c2fa383d4a3960714caef0c4f2';

    private static BlossomTestServer $server;

    public static function setUpBeforeClass(): void
    {
        self::$server = BlossomTestServer::boot();
    }

    public static function tearDownAfterClass(): void
    {
        self::$server->stop();
    }

    #[TestDox('BUD-11 — A token encoded as unpadded base64url authorises the endpoint')]
    public function testUnpaddedBase64UrlTokenIsAccepted(): void
    {
        $response = $this->preflightUpload(self::$server->authHeader('upload', self::DECLARED_HASH));

        self::assertSame(200, $response->status());
    }

    #[TestDox('BUD-11 — A token encoded as padded standard base64, as clients before BUD-11 send it, is still accepted')]
    public function testLegacyPaddedBase64TokenIsAccepted(): void
    {
        $response = $this->preflightUpload(self::$server->legacyAuthHeader('upload', self::DECLARED_HASH));

        self::assertSame(200, $response->status());
    }

    #[TestDox('BUD-11 — Credentials that are not base64 are rejected with 401')]
    public function testCredentialsThatAreNotBase64AreRejected(): void
    {
        $response = $this->preflightUpload('Nostr not*base64');

        self::assertSame(401, $response->status());
    }

    #[TestDox('BUD-11 — A token whose t tag names another verb is rejected with 401')]
    public function testTokenForAnotherVerbIsRejected(): void
    {
        $response = $this->preflightUpload(self::$server->authHeader('delete', self::DECLARED_HASH));

        self::assertSame(401, $response->status());
    }

    #[TestDox('BUD-11 — A valid token whose x tags do not name the implied blob is forbidden with 403')]
    public function testTokenForAnotherBlobIsForbidden(): void
    {
        $response = $this->preflightUpload(self::$server->authHeader('upload', str_repeat('d', 64)));

        self::assertSame(403, $response->status());
    }

    private function preflightUpload(string $authorization): HttpResponse
    {
        return self::$server->request('HEAD', '/upload', [
            'Authorization' => $authorization,
            'X-SHA-256' => self::DECLARED_HASH,
            'X-Content-Length' => '1024',
            'X-Content-Type' => 'image/png',
        ]);
    }
}
