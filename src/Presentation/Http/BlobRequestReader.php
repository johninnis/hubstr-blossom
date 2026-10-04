<?php

declare(strict_types=1);

namespace Innis\Hubstr\Blossom\Presentation\Http;

use Amp\ByteStream\BufferException;
use Amp\Http\HttpStatus;
use Amp\Http\Server\Request;
use Amp\Http\Server\Response;
use Amp\Http\Server\Router;
use Innis\Hubstr\Blossom\Domain\Exception\MalformedRouteValueException;
use Innis\Hubstr\Blossom\Infrastructure\Http\BlobStager;
use Innis\Hubstr\Blossom\Infrastructure\Http\ContentLengthHeader;
use Innis\Nostr\Blossom\Application\Port\PendingBlobSourceInterface;
use Innis\Nostr\Blossom\Domain\ValueObject\BlobHash;
use Innis\Nostr\Blossom\Domain\ValueObject\DeclaredBlob;
use Innis\Nostr\Blossom\Domain\ValueObject\HttpUrl;
use Innis\Nostr\Blossom\Domain\ValueObject\ListQuery;
use Innis\Nostr\Blossom\Domain\ValueObject\MimeType;
use Innis\Nostr\Core\Domain\Service\JsonWireFormat;
use Innis\Nostr\Core\Domain\ValueObject\Identity\PublicKey;

final readonly class BlobRequestReader
{
    private const int MIRROR_BODY_LIMIT = 8192;

    public function __construct(private BlobStager $stager)
    {
    }

    public function authHeader(Request $request): string
    {
        return $request->getHeader('authorization') ?? '';
    }

    public function blobSource(Request $request): PendingBlobSourceInterface
    {
        return new StreamPendingBlobSource($this->stager, $request);
    }

    public function bufferBody(Request $request, int $limit): string|Response
    {
        try {
            return $request->getBody()->buffer(limit: $limit);
        } catch (BufferException) {
            return Responder::failure(HttpStatus::PAYLOAD_TOO_LARGE, 'Request body exceeds the permitted size');
        }
    }

    private function routeValue(Request $request, string $name): string
    {
        $args = $request->getAttribute(Router::class);
        if (!is_array($args) || !isset($args[$name]) || !is_string($args[$name])) {
            return '';
        }

        return $args[$name];
    }

    public function routeHash(Request $request, string $name): BlobHash
    {
        $value = $this->routeValue($request, $name);
        $hash = BlobHash::tryFromHex($value);
        if (null === $hash) {
            throw MalformedRouteValueException::hash($value);
        }

        return $hash;
    }

    public function declaredBlob(Request $request): DeclaredBlob|Response|null
    {
        $hash = $request->getHeader('x-sha-256');
        $length = $request->getHeader('x-content-length');
        $type = $request->getHeader('x-content-type');

        if (null === $hash && null === $length && null === $type) {
            return null;
        }

        if (null === $length) {
            return Responder::failure(HttpStatus::LENGTH_REQUIRED, 'Missing X-Content-Length header');
        }

        $size = ContentLengthHeader::parse($length);
        if (null === $size) {
            return Responder::failure(HttpStatus::BAD_REQUEST, 'Invalid X-Content-Length header');
        }

        $blobHash = BlobHash::tryFromHex($hash ?? '');
        if (null === $blobHash) {
            return Responder::failure(HttpStatus::BAD_REQUEST, 'Invalid or missing X-SHA-256 header');
        }

        $mimeType = MimeType::tryFromString($type ?? '');
        if (null === $mimeType) {
            return Responder::failure(HttpStatus::BAD_REQUEST, 'Invalid or missing X-Content-Type header');
        }

        return new DeclaredBlob($blobHash, $size, $mimeType);
    }

    public function headerHash(Request $request, string $name): BlobHash|Response|null
    {
        $value = $request->getHeader($name);
        if (null === $value) {
            return null;
        }

        return BlobHash::tryFromHex($value) ?? Responder::failure(HttpStatus::BAD_REQUEST, 'Invalid X-SHA-256 header');
    }

    public function routePublicKey(Request $request, string $name): PublicKey
    {
        $value = $this->routeValue($request, $name);
        $pubkey = PublicKey::tryFromHex($value);
        if (null === $pubkey) {
            throw MalformedRouteValueException::publicKey($value);
        }

        return $pubkey;
    }

    public function listQuery(Request $request): ListQuery|Response
    {
        return ListQuery::tryFromQueryString($request->getUri()->getQuery())
            ?? Responder::failure(HttpStatus::BAD_REQUEST, 'Invalid list query parameters');
    }

    public function mirrorUrl(Request $request): HttpUrl|Response
    {
        $body = $this->bufferBody($request, self::MIRROR_BODY_LIMIT);
        if ($body instanceof Response) {
            return $body;
        }

        $url = JsonWireFormat::stringField(JsonWireFormat::decodeObject($body) ?? [], 'url');

        return HttpUrl::tryFromString($url ?? '')
            ?? Responder::failure(HttpStatus::BAD_REQUEST, 'Missing or invalid "url" field');
    }
}
