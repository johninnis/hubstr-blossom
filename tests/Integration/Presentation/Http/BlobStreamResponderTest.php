<?php

declare(strict_types=1);

namespace Innis\Hubstr\Blossom\Tests\Integration\Presentation\Http;

use Amp\Http\HttpStatus;
use Innis\Hubstr\Blossom\Infrastructure\Filesystem\FileByteStreamer;
use Innis\Hubstr\Blossom\Presentation\Http\BlobStreamResponder;
use Innis\Hubstr\Blossom\Tests\Support\TemporaryDirectory;
use Innis\Nostr\Blossom\Application\DTO\RetrievedBlob;
use Innis\Nostr\Blossom\Domain\ValueObject\BlobDescriptor;
use Innis\Nostr\Blossom\Domain\ValueObject\BlobHash;
use Innis\Nostr\Blossom\Domain\ValueObject\HttpUrl;
use Innis\Nostr\Blossom\Domain\ValueObject\MimeType;
use Innis\Nostr\Core\Domain\ValueObject\Timestamp;
use PHPUnit\Framework\TestCase;

use function Amp\ByteStream\buffer;

final class BlobStreamResponderTest extends TestCase
{
    private const string CONTENT = 'abcdefghijklmnopqrstuvwxyz0123456789';

    private TemporaryDirectory $directory;

    private RetrievedBlob $blob;

    protected function setUp(): void
    {
        $this->directory = TemporaryDirectory::create();
        $path = $this->directory->path('blob');
        file_put_contents($path, self::CONTENT);
        $this->blob = new RetrievedBlob($this->descriptor(), $path);
    }

    protected function tearDown(): void
    {
        $this->directory->remove();
    }

    public function testFullGetStreamsTheWholeEntityWithCachingHeaders(): void
    {
        $response = $this->responder()->get($this->blob, null, null);

        self::assertSame(HttpStatus::OK, $response->getStatus());
        self::assertSame(self::CONTENT, buffer($response->getBody()));
        self::assertSame('image/png', $response->getHeader('content-type'));
        self::assertSame((string) strlen(self::CONTENT), $response->getHeader('content-length'));
        self::assertSame('bytes', $response->getHeader('accept-ranges'));
        self::assertSame('public, max-age=31536000, immutable', $response->getHeader('cache-control'));
        self::assertSame($this->etag(), $response->getHeader('etag'));
    }

    public function testRangeRequestStreamsExactlyTheRequestedBytes(): void
    {
        $response = $this->responder()->get($this->blob, 'bytes=10-19', null);

        self::assertSame(HttpStatus::PARTIAL_CONTENT, $response->getStatus());
        self::assertSame('klmnopqrst', buffer($response->getBody()));
        self::assertSame('10', $response->getHeader('content-length'));
        self::assertSame('bytes 10-19/36', $response->getHeader('content-range'));
    }

    public function testSuffixRangeStreamsTheTailOfTheEntity(): void
    {
        $response = $this->responder()->get($this->blob, 'bytes=-6', null);

        self::assertSame(HttpStatus::PARTIAL_CONTENT, $response->getStatus());
        self::assertSame('456789', buffer($response->getBody()));
        self::assertSame('bytes 30-35/36', $response->getHeader('content-range'));
    }

    public function testUnsatisfiableRangeReturns416WithoutABody(): void
    {
        $response = $this->responder()->get($this->blob, 'bytes=200-', null);

        self::assertSame(HttpStatus::RANGE_NOT_SATISFIABLE, $response->getStatus());
        self::assertSame('bytes */36', $response->getHeader('content-range'));
        self::assertSame('', buffer($response->getBody()));
    }

    public function testMatchingIfNoneMatchReturnsNotModifiedWithoutABody(): void
    {
        $response = $this->responder()->get($this->blob, null, 'W/'.$this->etag());

        self::assertSame(HttpStatus::NOT_MODIFIED, $response->getStatus());
        self::assertNull($response->getHeader('content-length'));
        self::assertSame($this->etag(), $response->getHeader('etag'));
        self::assertSame('', buffer($response->getBody()));
    }

    public function testHeadReturnsEntityHeadersWithoutABody(): void
    {
        $response = $this->responder()->head($this->blob, null);

        self::assertSame(HttpStatus::OK, $response->getStatus());
        self::assertSame((string) strlen(self::CONTENT), $response->getHeader('content-length'));
        self::assertNull($response->getHeader('content-range'));
        self::assertSame('', buffer($response->getBody()));
    }

    public function testHeadHonoursIfNoneMatch(): void
    {
        $response = $this->responder()->head($this->blob, $this->etag());

        self::assertSame(HttpStatus::NOT_MODIFIED, $response->getStatus());
    }

    private function responder(): BlobStreamResponder
    {
        return new BlobStreamResponder(new FileByteStreamer());
    }

    private function descriptor(): BlobDescriptor
    {
        $url = HttpUrl::tryFromString('https://blossom.example.com/'.$this->hash()->toHex());
        self::assertNotNull($url);

        return new BlobDescriptor(
            url: $url,
            sha256: $this->hash(),
            size: strlen(self::CONTENT),
            type: MimeType::fromString('image/png'),
            uploaded: Timestamp::fromInt(1_700_000_000),
        );
    }

    private function hash(): BlobHash
    {
        $hash = BlobHash::tryFromHex(hash('sha256', self::CONTENT));
        self::assertNotNull($hash);

        return $hash;
    }

    private function etag(): string
    {
        return '"'.$this->hash()->toHex().'"';
    }
}
