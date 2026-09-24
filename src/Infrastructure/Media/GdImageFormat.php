<?php

declare(strict_types=1);

namespace Innis\Hubstr\Blossom\Infrastructure\Media;

use GdImage;
use Innis\Hubstr\Blossom\Domain\Exception\MediaProcessingException;
use Innis\Nostr\Blossom\Domain\ValueObject\MimeType;

enum GdImageFormat: string
{
    case Jpeg = 'image/jpeg';
    case Png = 'image/png';
    case Webp = 'image/webp';
    case Gif = 'image/gif';

    private const int JPEG_QUALITY = 85;
    private const int PNG_COMPRESSION_LEVEL = 6;
    private const int WEBP_QUALITY = 80;
    private const int ANIMATION_SCAN_CHUNK = 65536;
    private const string GIF_FRAME_CONTROL_MARKER = '/\x00\x21\xF9\x04/s';
    private const int GIF_FRAME_CONTROL_MARKER_CARRY = 3;
    private const int WEBP_HEADER_LENGTH = 21;
    private const int WEBP_FORM_TYPE_OFFSET = 8;
    private const int WEBP_FIRST_CHUNK_OFFSET = 12;
    private const int WEBP_VP8X_FLAGS_OFFSET = 20;
    private const int WEBP_ANIMATION_FLAG = 0x02;

    public static function forMimeType(MimeType $mimeType): ?self
    {
        return self::tryFrom((string) $mimeType);
    }

    public function isAnimated(string $path): bool
    {
        return match ($this) {
            self::Gif => self::isAnimatedGif($path),
            self::Webp => self::isAnimatedWebp($path),
            self::Jpeg, self::Png => false,
        };
    }

    public function decode(string $path): GdImage
    {
        $image = match ($this) {
            self::Jpeg => @imagecreatefromjpeg($path),
            self::Png => @imagecreatefrompng($path),
            self::Webp => @imagecreatefromwebp($path),
            self::Gif => @imagecreatefromgif($path),
        };

        if (false === $image) {
            throw MediaProcessingException::failedToLoad($path);
        }

        if ($this->preservesTransparency()) {
            imagealphablending($image, false);
            imagesavealpha($image, true);
        }

        // Deliberate: the orientation is baked into the pixels because the re-encode keeps no metadata at all — see ADR-0010
        return $this->carriesExif() ? ExifOrientation::read($path)->upright($image) : $image;
    }

    public function encode(GdImage $image, string $path): void
    {
        $encoded = match ($this) {
            self::Jpeg => imagejpeg($image, $path, self::JPEG_QUALITY),
            self::Png => imagepng($image, $path, self::PNG_COMPRESSION_LEVEL),
            self::Webp => imagewebp($image, $path, self::WEBP_QUALITY),
            self::Gif => imagegif($image, $path),
        };

        if (false === $encoded) {
            throw MediaProcessingException::failedToOptimise($path);
        }
    }

    private function preservesTransparency(): bool
    {
        return match ($this) {
            self::Png, self::Webp => true,
            self::Jpeg, self::Gif => false,
        };
    }

    private function carriesExif(): bool
    {
        return self::Jpeg === $this;
    }

    private static function isAnimatedGif(string $path): bool
    {
        $handle = fopen($path, 'r');
        if (false === $handle) {
            return false;
        }

        $frames = 0;
        $carry = '';
        try {
            while ($frames < 2 && !feof($handle)) {
                $chunk = fread($handle, self::ANIMATION_SCAN_CHUNK);
                if (false === $chunk) {
                    break;
                }

                $frames += preg_match_all(self::GIF_FRAME_CONTROL_MARKER, $carry.$chunk);
                $carry = substr($chunk, -self::GIF_FRAME_CONTROL_MARKER_CARRY);
            }
        } finally {
            fclose($handle);
        }

        return $frames > 1;
    }

    private static function isAnimatedWebp(string $path): bool
    {
        $handle = fopen($path, 'r');
        if (false === $handle) {
            return false;
        }

        try {
            $header = fread($handle, self::WEBP_HEADER_LENGTH);
        } finally {
            fclose($handle);
        }

        if (!is_string($header) || self::WEBP_HEADER_LENGTH !== strlen($header)) {
            return false;
        }

        if (!str_starts_with($header, 'RIFF') || 'WEBP' !== substr($header, self::WEBP_FORM_TYPE_OFFSET, 4) || 'VP8X' !== substr($header, self::WEBP_FIRST_CHUNK_OFFSET, 4)) {
            return false;
        }

        return 0 !== (ord($header[self::WEBP_VP8X_FLAGS_OFFSET]) & self::WEBP_ANIMATION_FLAG);
    }
}
