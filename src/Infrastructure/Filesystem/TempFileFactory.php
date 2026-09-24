<?php

declare(strict_types=1);

namespace Innis\Hubstr\Blossom\Infrastructure\Filesystem;

use Innis\Hubstr\Blossom\Domain\Exception\BlobStorageException;
use Innis\Hubstr\Core\Infrastructure\Filesystem\Directory;

final readonly class TempFileFactory
{
    public function __construct(private string $directory)
    {
    }

    public function create(string $prefix): string
    {
        Directory::atPath($this->directory)->ensure();

        $path = tempnam($this->directory, $prefix);
        if (false === $path) {
            throw BlobStorageException::failedToCreateTempFile($this->directory);
        }

        return $path;
    }
}
