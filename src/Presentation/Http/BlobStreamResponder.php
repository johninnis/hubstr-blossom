<?php

declare(strict_types=1);

namespace Innis\Hubstr\Blossom\Presentation\Http;

use Amp\Http\HttpStatus;
use Amp\Http\Server\Response;
use Innis\Hubstr\Blossom\Infrastructure\Filesystem\FileByteStreamer;
use Innis\Nostr\Blossom\Application\DTO\RetrievedBlob;
use Innis\Nostr\Blossom\Domain\ValueObject\BlobDescriptor;

final readonly class BlobStreamResponder
{
    private const string CACHE_CONTROL = 'public, max-age=31536000, immutable';

    public function __construct(
        private FileByteStreamer $bytes,
    ) {
    }

    public function get(RetrievedBlob $blob, ?string $rangeHeader, ?string $ifNoneMatch): Response
    {
        $descriptor = $blob->getDescriptor();
        $etag = self::etag($blob);

        if (self::matchesEtag($ifNoneMatch, $etag)) {
            return self::notModified($etag);
        }

        $range = ByteRange::parse($rangeHeader, $descriptor->getSize());

        if (null !== $range && !$range->isSatisfiable()) {
            return self::rangeNotSatisfiable($descriptor, $etag);
        }

        return $this->body($blob, $range);
    }

    public function head(RetrievedBlob $blob, ?string $ifNoneMatch): Response
    {
        $descriptor = $blob->getDescriptor();
        $etag = self::etag($blob);

        if (self::matchesEtag($ifNoneMatch, $etag)) {
            return self::notModified($etag);
        }

        return new Response(HttpStatus::OK, self::entityHeaders($descriptor, $descriptor->getSize(), $etag));
    }

    private function body(RetrievedBlob $blob, ?ByteRange $range): Response
    {
        $descriptor = $blob->getDescriptor();
        $size = $descriptor->getSize();
        $start = null === $range ? 0 : $range->getStart();
        $length = null === $range ? $size : $range->getLength();

        $headers = self::entityHeaders($descriptor, $length, self::etag($blob));

        if (null !== $range) {
            $headers['content-range'] = sprintf('bytes %d-%d/%d', $range->getStart(), $range->getStart() + $range->getLength() - 1, $size);
        }

        $status = null === $range ? HttpStatus::OK : HttpStatus::PARTIAL_CONTENT;

        return new Response($status, $headers, $this->bytes->stream($blob->getPath(), $start, $length));
    }

    private static function rangeNotSatisfiable(BlobDescriptor $descriptor, string $etag): Response
    {
        return new Response(HttpStatus::RANGE_NOT_SATISFIABLE, [
            'content-type' => (string) $descriptor->getType(),
            'content-range' => 'bytes */'.$descriptor->getSize(),
            'accept-ranges' => 'bytes',
            'etag' => $etag,
        ]);
    }

    /**
     * @return array<non-empty-string, string>
     */
    private static function entityHeaders(BlobDescriptor $descriptor, int $length, string $etag): array
    {
        return [
            'content-type' => (string) $descriptor->getType(),
            'content-length' => (string) $length,
            ...self::commonHeaders($etag),
        ];
    }

    /**
     * @return array<non-empty-string, string>
     */
    private static function commonHeaders(string $etag): array
    {
        return [
            'accept-ranges' => 'bytes',
            'cache-control' => self::CACHE_CONTROL,
            'etag' => $etag,
        ];
    }

    private static function etag(RetrievedBlob $blob): string
    {
        return '"'.$blob->getDescriptor()->getSha256()->toHex().'"';
    }

    private static function matchesEtag(?string $ifNoneMatch, string $etag): bool
    {
        if (null === $ifNoneMatch) {
            return false;
        }

        foreach (explode(',', $ifNoneMatch) as $candidate) {
            $candidate = trim($candidate);
            if ('*' === $candidate) {
                return true;
            }

            if (str_starts_with($candidate, 'W/')) {
                $candidate = substr($candidate, 2);
            }

            if ($candidate === $etag) {
                return true;
            }
        }

        return false;
    }

    private static function notModified(string $etag): Response
    {
        return new Response(HttpStatus::NOT_MODIFIED, self::commonHeaders($etag));
    }
}
