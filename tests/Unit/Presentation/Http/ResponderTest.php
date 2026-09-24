<?php

declare(strict_types=1);

namespace Innis\Hubstr\Blossom\Tests\Unit\Presentation\Http;

use Amp\Http\HttpStatus;
use Innis\Hubstr\Blossom\Presentation\Http\Responder;
use Innis\Nostr\Blossom\Domain\Collection\BlobDescriptorCollection;
use Innis\Nostr\Blossom\Domain\Failure\AuthenticationFailure;
use Innis\Nostr\Blossom\Domain\Failure\AuthorisationFailure;
use Innis\Nostr\Blossom\Domain\Failure\BlobNotFoundFailure;
use Innis\Nostr\Blossom\Domain\Failure\BlobTooLargeFailure;
use Innis\Nostr\Blossom\Domain\Failure\RemoteFetchFailure;
use Innis\Nostr\Blossom\Domain\Failure\UnsupportedMimeTypeFailure;
use Innis\Nostr\Blossom\Domain\ValueObject\HttpUrl;
use Innis\Nostr\Blossom\Domain\ValueObject\MimeType;
use PHPUnit\Framework\TestCase;

final class ResponderTest extends TestCase
{
    public function testErrorMapsEachDomainErrorToStatus(): void
    {
        self::assertSame(HttpStatus::UNAUTHORIZED, Responder::error(AuthenticationFailure::missingHeader())->getStatus());
        self::assertSame(HttpStatus::FORBIDDEN, Responder::error(AuthorisationFailure::notTenant())->getStatus());
        self::assertSame(HttpStatus::NOT_FOUND, Responder::error(BlobNotFoundFailure::forHash('abc'))->getStatus());
        self::assertSame(HttpStatus::PAYLOAD_TOO_LARGE, Responder::error(BlobTooLargeFailure::forSize(10, 5))->getStatus());
        self::assertSame(HttpStatus::UNSUPPORTED_MEDIA_TYPE, Responder::error(UnsupportedMimeTypeFailure::forType(MimeType::fromString('x/y')))->getStatus());
        self::assertSame(HttpStatus::BAD_GATEWAY, Responder::error(RemoteFetchFailure::forUrl($this->url('http://x')))->getStatus());
    }

    public function testErrorSetsReasonHeaderAndContentType(): void
    {
        $error = BlobNotFoundFailure::forHash('deadbeef');
        $response = Responder::error($error);

        self::assertSame(HttpStatus::NOT_FOUND, $response->getStatus());
        self::assertSame($error->getMessage(), $response->getHeader('x-reason'));
        self::assertSame('application/json', $response->getHeader('content-type'));
    }

    public function testRespondSerialisesDomainResultAsJson(): void
    {
        $response = Responder::respond(new BlobDescriptorCollection([]));

        self::assertSame(HttpStatus::OK, $response->getStatus());
        self::assertSame('application/json', $response->getHeader('content-type'));
    }

    public function testRespondReturnsEmptyOkForNull(): void
    {
        $response = Responder::respond(null);

        self::assertSame(HttpStatus::OK, $response->getStatus());
        self::assertNull($response->getHeader('content-type'));
    }

    public function testRespondMapsBlossomFailure(): void
    {
        $response = Responder::respond(AuthenticationFailure::missingHeader());

        self::assertSame(HttpStatus::UNAUTHORIZED, $response->getStatus());
    }

    private function url(string $value): HttpUrl
    {
        $url = HttpUrl::tryFromString($value);
        self::assertNotNull($url);

        return $url;
    }
}
