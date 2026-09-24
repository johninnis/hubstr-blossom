<?php

declare(strict_types=1);

namespace Innis\Hubstr\Blossom\Domain\Exception;

use Innis\Hubstr\Core\Domain\Exception\HubstrException;

final class BlobStorageException extends HubstrException
{
    public static function failedToMoveBlob(string $targetPath): self
    {
        return new self(sprintf('Failed to move blob into place: %s', $targetPath));
    }

    public static function failedToCreateTempFile(string $directory): self
    {
        return new self(sprintf('Failed to create temporary file in: %s', $directory));
    }
}
