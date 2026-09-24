<?php

declare(strict_types=1);

namespace Innis\Hubstr\Blossom\Domain\Exception;

use Innis\Hubstr\Core\Domain\Exception\HubstrException;

final class MediaProcessingException extends HubstrException
{
    public static function failedToLoad(string $path): self
    {
        return new self(sprintf('Failed to load image: %s', $path));
    }

    public static function failedToOrient(int $exifOrientation): self
    {
        return new self(sprintf('Failed to correct EXIF orientation %d', $exifOrientation));
    }

    public static function failedToOptimise(string $path): self
    {
        return new self(sprintf('Failed to optimise image: %s', $path));
    }
}
