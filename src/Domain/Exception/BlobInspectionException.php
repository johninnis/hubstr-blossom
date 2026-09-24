<?php

declare(strict_types=1);

namespace Innis\Hubstr\Blossom\Domain\Exception;

use Innis\Hubstr\Core\Domain\Exception\HubstrException;

final class BlobInspectionException extends HubstrException
{
    public static function sizeUnavailable(string $path): self
    {
        return new self(sprintf('Unable to determine the size of blob: %s', $path));
    }

    public static function unhashable(string $path): self
    {
        return new self(sprintf('Unable to hash blob: %s', $path));
    }
}
