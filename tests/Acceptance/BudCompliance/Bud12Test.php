<?php

declare(strict_types=1);

namespace Innis\Hubstr\Blossom\Tests\Acceptance\BudCompliance;

use Innis\Hubstr\Blossom\Tests\Support\BlossomTestServer;
use Innis\Hubstr\Blossom\Tests\Support\GdImageFixture;
use Innis\Hubstr\Blossom\Tests\Support\JsonBody;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;

final class Bud12Test extends TestCase
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

    #[TestDox('BUD-12 — DELETE /<sha256> removes a blob held by the authenticated tenant')]
    public function testDeleteRemovesBlob(): void
    {
        $content = $this->uniquePng();
        $sha256 = hash('sha256', $content);

        self::$server->request('PUT', '/upload', [
            'Authorization' => self::$server->authHeader('upload', $sha256),
            'Content-Type' => 'image/png',
        ], $content);

        $delete = self::$server->request('DELETE', '/'.$sha256, [
            'Authorization' => self::$server->authHeader('delete', $sha256),
        ]);
        self::assertSame(200, $delete->status());

        $get = self::$server->request('GET', '/'.$sha256);
        self::assertSame(404, $get->status());
    }

    #[TestDox('BUD-12 — A delete without a valid authorization event is rejected with 401')]
    public function testDeleteRejectsUnauthenticated(): void
    {
        $response = self::$server->request('DELETE', '/'.hash('sha256', 'some blob'));

        self::assertSame(401, $response->status());
    }

    #[TestDox('BUD-12 — GET /list/<pubkey> returns the blobs stored for the authenticated tenant')]
    public function testListReturnsUploadedBlobs(): void
    {
        $content = $this->uniquePng();
        $sha256 = hash('sha256', $content);

        self::$server->request('PUT', '/upload', [
            'Authorization' => self::$server->authHeader('upload', $sha256),
            'Content-Type' => 'image/png',
        ], $content);

        $response = self::$server->request('GET', '/list/'.self::$server->ownerPubkeyHex(), [
            'Authorization' => self::$server->authHeader('list'),
        ]);

        self::assertSame(200, $response->status());
        $json = json_decode($response->body(), true);
        self::assertIsArray($json);
        self::assertNotEmpty($json);
    }

    #[TestDox('BUD-12 — The /list/<pubkey> preflight advertises read methods but no write methods')]
    public function testOptionsOnListPathAdvertisesGetNotWriteMethods(): void
    {
        $response = self::$server->request('OPTIONS', '/list/'.self::$server->ownerPubkeyHex());

        self::assertSame(204, $response->status());
        self::assertSame('GET, HEAD, OPTIONS', $response->header('access-control-allow-methods'));
    }

    #[TestDox('BUD-12 — The list endpoint ignores a query parameter it does not know')]
    public function testListIgnoresAnUnknownQueryParameter(): void
    {
        $response = self::$server->request('GET', '/list/'.self::$server->ownerPubkeyHex().'?cursor=not-a-valid-hash', [
            'Authorization' => self::$server->authHeader('list'),
        ]);

        self::assertSame(200, $response->status());
        self::assertIsArray(json_decode($response->body(), true));
    }

    #[TestDox('BUD-12 — Listing a pubkey other than the authenticated tenant is forbidden')]
    public function testListRejectsAnotherPubkey(): void
    {
        $response = self::$server->request('GET', '/list/'.str_repeat('cd', 32), [
            'Authorization' => self::$server->authHeader('list'),
        ]);

        self::assertSame(403, $response->status());
    }

    #[TestDox('BUD-12 — The list endpoint honours the limit pagination parameter')]
    public function testListSupportsLimitParameter(): void
    {
        for ($i = 0; $i < 3; ++$i) {
            $content = $this->uniquePng();
            self::$server->request('PUT', '/upload', [
                'Authorization' => self::$server->authHeader('upload', hash('sha256', $content)),
                'Content-Type' => 'image/png',
            ], $content);
        }

        $response = self::$server->request('GET', '/list/'.self::$server->ownerPubkeyHex().'?limit=2', [
            'Authorization' => self::$server->authHeader('list'),
        ]);

        self::assertSame(200, $response->status());
        self::assertCount(2, JsonBody::from($response->body())->toArray());
    }

    #[TestDox('BUD-12 — A tenant can neither list nor delete a blob held by another tenant')]
    public function testTenantCannotListOrDeleteAnotherTenantsBlob(): void
    {
        $content = $this->uniquePng();
        $sha256 = hash('sha256', $content);

        $upload = self::$server->request('PUT', '/upload', [
            'Authorization' => self::$server->authHeader('upload', $sha256),
            'Content-Type' => 'image/png',
        ], $content);
        self::assertSame(200, $upload->status());

        $list = self::$server->request('GET', '/list/'.self::$server->secondTenantPubkeyHex(), [
            'Authorization' => self::$server->authHeaderFor(self::$server->secondTenant(), 'list'),
        ]);
        self::assertSame(200, $list->status());
        self::assertSame([], json_decode($list->body(), true));

        $delete = self::$server->request('DELETE', '/'.$sha256, [
            'Authorization' => self::$server->authHeaderFor(self::$server->secondTenant(), 'delete', $sha256),
        ]);
        self::assertSame(404, $delete->status());

        self::assertSame(200, self::$server->request('GET', '/'.$sha256)->status());
    }

    #[TestDox('BUD-12 — Deduplicated bytes are removed only when the last holding tenant deletes them')]
    public function testSharedBytesSurviveUntilLastTenantDeletes(): void
    {
        $content = $this->uniquePng();
        $sha256 = hash('sha256', $content);

        $first = self::$server->request('PUT', '/upload', [
            'Authorization' => self::$server->authHeader('upload', $sha256),
            'Content-Type' => 'image/png',
        ], $content);
        self::assertSame(200, $first->status());

        $second = self::$server->request('PUT', '/upload', [
            'Authorization' => self::$server->authHeaderFor(self::$server->secondTenant(), 'upload', $sha256),
            'Content-Type' => 'image/png',
        ], $content);
        self::assertSame(200, $second->status());

        $deleteFirst = self::$server->request('DELETE', '/'.$sha256, [
            'Authorization' => self::$server->authHeader('delete', $sha256),
        ]);
        self::assertSame(200, $deleteFirst->status());
        self::assertSame(200, self::$server->request('GET', '/'.$sha256)->status());

        $deleteSecond = self::$server->request('DELETE', '/'.$sha256, [
            'Authorization' => self::$server->authHeaderFor(self::$server->secondTenant(), 'delete', $sha256),
        ]);
        self::assertSame(200, $deleteSecond->status());
        self::assertSame(404, self::$server->request('GET', '/'.$sha256)->status());
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
