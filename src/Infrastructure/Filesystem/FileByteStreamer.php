<?php

declare(strict_types=1);

namespace Innis\Hubstr\Blossom\Infrastructure\Filesystem;

use Amp\ByteStream\ReadableIterableStream;
use Amp\ByteStream\ReadableStream;
use Generator;

use function Amp\File\openFile;

final readonly class FileByteStreamer
{
    private const int CHUNK_SIZE = 65536;

    public function stream(string $path, int $offset, int $length): ReadableStream
    {
        return new ReadableIterableStream($this->readChunks($path, $offset, $length));
    }

    private function readChunks(string $path, int $offset, int $length): Generator
    {
        $file = openFile($path, 'r');

        try {
            if ($offset > 0) {
                $file->seek($offset);
            }

            $remaining = $length;
            while ($remaining > 0) {
                $chunk = $file->read(length: min(self::CHUNK_SIZE, $remaining));
                if (null === $chunk) {
                    break;
                }

                $remaining -= strlen($chunk);
                yield $chunk;
            }
        } finally {
            $file->close();
        }
    }
}
