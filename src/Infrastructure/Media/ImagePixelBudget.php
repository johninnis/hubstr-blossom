<?php

declare(strict_types=1);

namespace Innis\Hubstr\Blossom\Infrastructure\Media;

use InvalidArgumentException;

final readonly class ImagePixelBudget
{
    public function __construct(private int $maxPixels)
    {
        if ($maxPixels < 1) {
            throw new InvalidArgumentException(sprintf('maxPixels must be a positive integer, got %d', $maxPixels));
        }
    }

    public function admits(int $width, int $height): bool
    {
        return $width > 0 && $height > 0 && $width * $height <= $this->maxPixels;
    }
}
