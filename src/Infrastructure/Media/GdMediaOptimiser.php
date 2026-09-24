<?php

declare(strict_types=1);

namespace Innis\Hubstr\Blossom\Infrastructure\Media;

use Innis\Hubstr\Blossom\Infrastructure\Filesystem\TempFileFactory;
use Innis\Nostr\Blossom\Application\DTO\PendingBlob;
use Innis\Nostr\Blossom\Application\Port\MediaOptimiserInterface;
use Innis\Nostr\Blossom\Domain\ValueObject\MimeType;
use Override;
use Throwable;

final readonly class GdMediaOptimiser implements MediaOptimiserInterface
{
    private const string TEMP_PREFIX = 'blossom_optimised_';

    public function __construct(
        private TempFileFactory $tempFiles,
        private ImagePixelBudget $pixelBudget,
    ) {
    }

    #[Override]
    public function supports(MimeType $mimeType): bool
    {
        return null !== GdImageFormat::forMimeType($mimeType);
    }

    #[Override]
    public function optimise(PendingBlob $source): PendingBlob
    {
        $format = GdImageFormat::forMimeType($source->getMimeType());
        if (null === $format || $format->isAnimated($source->getPath()) || !$this->admitsByHeaderDimensions($source->getPath())) {
            return $source;
        }

        $outputPath = $this->tempFiles->create(self::TEMP_PREFIX);

        try {
            $format->encode($format->decode($source->getPath()), $outputPath);
        } catch (Throwable $failure) {
            unlink($outputPath);

            throw $failure;
        }

        if (!$this->isSmaller($outputPath, $source->getPath())) {
            unlink($outputPath);

            return $source;
        }

        return new PendingBlob($outputPath, $source->getMimeType());
    }

    private function admitsByHeaderDimensions(string $path): bool
    {
        $dimensions = @getimagesize($path);

        return false !== $dimensions && $this->pixelBudget->admits($dimensions[0], $dimensions[1]);
    }

    private function isSmaller(string $candidatePath, string $sourcePath): bool
    {
        $candidateSize = filesize($candidatePath);
        $sourceSize = filesize($sourcePath);

        return false !== $candidateSize && false !== $sourceSize && $candidateSize < $sourceSize;
    }
}
