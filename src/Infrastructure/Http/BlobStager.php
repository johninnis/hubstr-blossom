<?php

declare(strict_types=1);

namespace Innis\Hubstr\Blossom\Infrastructure\Http;

use Amp\ByteStream\ReadableStream;
use Amp\File\File;
use Amp\Http\HttpMessage;
use Innis\Hubstr\Blossom\Infrastructure\Filesystem\TempFileFactory;
use Innis\Nostr\Blossom\Application\DTO\PendingBlob;
use Innis\Nostr\Blossom\Domain\Failure\BlobTooLargeFailure;
use Innis\Nostr\Blossom\Domain\ValueObject\MimeType;
use InvalidArgumentException;

use function Amp\File\openFile;

final readonly class BlobStager
{
    private const string TEMP_PREFIX = 'blossom_staged_';

    public function __construct(
        private TempFileFactory $tempFiles,
        private int $maxBytes,
    ) {
        if ($maxBytes < 1) {
            throw new InvalidArgumentException(sprintf('maxBytes must be a positive integer, got %d', $maxBytes));
        }
    }

    public function stage(HttpMessage $message, ReadableStream $body): PendingBlob|BlobTooLargeFailure
    {
        $declaredBytes = ContentLengthHeader::parse($message->getHeader('content-length'));
        if (null !== $declaredBytes && $declaredBytes > $this->maxBytes) {
            return BlobTooLargeFailure::beyondMaximum($this->maxBytes);
        }

        $path = $this->tempFiles->create(self::TEMP_PREFIX);
        $file = openFile($path, 'w');
        $kept = false;

        try {
            $kept = $this->copyWithinCap($body, $file);
        } finally {
            $file->close();
            if (!$kept) {
                unlink($path);
            }
        }

        return $kept
            ? new PendingBlob($path, MimeType::fromString($message->getHeader('content-type') ?? ''))
            : BlobTooLargeFailure::beyondMaximum($this->maxBytes);
    }

    private function copyWithinCap(ReadableStream $body, File $file): bool
    {
        $written = 0;
        while (null !== ($chunk = $body->read())) {
            $written += strlen($chunk);
            if ($written > $this->maxBytes) {
                return false;
            }

            $file->write($chunk);
        }

        return true;
    }
}
