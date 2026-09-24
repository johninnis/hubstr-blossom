<?php

declare(strict_types=1);

namespace Innis\Hubstr\Blossom\Infrastructure\Filesystem;

use GdImage;
use Innis\Hubstr\Blossom\Domain\Exception\BlobInspectionException;
use Innis\Hubstr\Blossom\Infrastructure\Media\ImagePixelBudget;
use Innis\Nostr\Blossom\Application\Port\BlobInspectorInterface;
use Innis\Nostr\Blossom\Domain\ValueObject\BlobHash;
use Innis\Nostr\Blossom\Domain\ValueObject\IncomingBlob;
use Innis\Nostr\Blossom\Domain\ValueObject\MediaMetadata;
use Innis\Nostr\Blossom\Domain\ValueObject\MimeType;
use kornrunner\Blurhash\Blurhash;
use Override;

final readonly class FilesystemBlobInspector implements BlobInspectorInterface
{
    private const int BLURHASH_SAMPLE_MAX = 32;
    private const int BLURHASH_COMPONENTS_X = 4;
    private const int BLURHASH_COMPONENTS_Y = 3;

    public function __construct(private ImagePixelBudget $pixelBudget)
    {
    }

    #[Override]
    public function inspect(string $tempPath): IncomingBlob
    {
        return $this->incomingBlob($tempPath, null);
    }

    #[Override]
    public function inspectWithMedia(string $tempPath): IncomingBlob
    {
        return $this->incomingBlob($tempPath, $this->mediaMetadata($tempPath));
    }

    private function incomingBlob(string $tempPath, ?MediaMetadata $media): IncomingBlob
    {
        $size = filesize($tempPath);
        if (false === $size) {
            throw BlobInspectionException::sizeUnavailable($tempPath);
        }

        $hashHex = hash_file('sha256', $tempPath);
        $hash = false === $hashHex ? null : BlobHash::tryFromHex($hashHex);
        if (null === $hash) {
            throw BlobInspectionException::unhashable($tempPath);
        }

        return new IncomingBlob($hash, $size, $media, $this->detectMimeType($tempPath));
    }

    private function detectMimeType(string $tempPath): ?MimeType
    {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if (false === $finfo) {
            return null;
        }

        $detected = finfo_file($finfo, $tempPath);

        return is_string($detected) && '' !== $detected ? MimeType::fromString($detected) : null;
    }

    private function mediaMetadata(string $tempPath): ?MediaMetadata
    {
        $dimensions = @getimagesize($tempPath);
        if (false === $dimensions) {
            return null;
        }

        $blurhash = $this->pixelBudget->admits($dimensions[0], $dimensions[1])
            ? $this->blurhash($tempPath)
            : null;

        return new MediaMetadata(sprintf('%dx%d', $dimensions[0], $dimensions[1]), $blurhash);
    }

    private function blurhash(string $tempPath): ?string
    {
        $contents = file_get_contents($tempPath);
        if (false === $contents) {
            return null;
        }

        $image = @imagecreatefromstring($contents);
        if (false === $image) {
            return null;
        }

        $pixels = $this->pixels($this->downsample($image));

        return Blurhash::encode($pixels, self::BLURHASH_COMPONENTS_X, self::BLURHASH_COMPONENTS_Y);
    }

    private function downsample(GdImage $image): GdImage
    {
        $width = imagesx($image);
        $height = imagesy($image);
        $longestSide = max($width, $height);
        if ($longestSide <= self::BLURHASH_SAMPLE_MAX) {
            return $image;
        }

        $scale = self::BLURHASH_SAMPLE_MAX / $longestSide;
        $targetWidth = max(1, (int) round($width * $scale));
        $targetHeight = max(1, (int) round($height * $scale));

        $target = imagecreatetruecolor($targetWidth, $targetHeight);
        imagecopyresampled($target, $image, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height);

        return $target;
    }

    /**
     * @return list<list<array{int, int, int}>>
     */
    private function pixels(GdImage $image): array
    {
        $width = imagesx($image);
        $height = imagesy($image);

        $rows = [];
        for ($y = 0; $y < $height; ++$y) {
            $row = [];
            for ($x = 0; $x < $width; ++$x) {
                $rgb = imagecolorat($image, $x, $y);
                $row[] = [($rgb >> 16) & 0xFF, ($rgb >> 8) & 0xFF, $rgb & 0xFF];
            }
            $rows[] = $row;
        }

        return $rows;
    }
}
