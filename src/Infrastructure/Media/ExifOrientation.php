<?php

declare(strict_types=1);

namespace Innis\Hubstr\Blossom\Infrastructure\Media;

use GdImage;
use Innis\Hubstr\Blossom\Domain\Exception\MediaProcessingException;

enum ExifOrientation: int
{
    case Upright = 1;
    case MirroredHorizontally = 2;
    case RotatedHalfTurn = 3;
    case MirroredVertically = 4;
    case Transposed = 5;
    case RotatedQuarterTurnAnticlockwise = 6;
    case Transversed = 7;
    case RotatedQuarterTurnClockwise = 8;

    private const string TAG = 'Orientation';
    private const int QUARTER_TURN_CLOCKWISE = -90;
    private const int QUARTER_TURN_ANTICLOCKWISE = 90;
    private const int HALF_TURN = 180;

    public static function read(string $jpegPath): self
    {
        $exif = @exif_read_data($jpegPath);
        $tag = is_array($exif) ? ($exif[self::TAG] ?? null) : null;

        return is_int($tag) ? (self::tryFrom($tag) ?? self::Upright) : self::Upright;
    }

    public function upright(GdImage $stored): GdImage
    {
        return match ($this) {
            self::Upright => $stored,
            self::MirroredHorizontally => $this->flipped($stored, IMG_FLIP_HORIZONTAL),
            self::RotatedHalfTurn => $this->rotated($stored, self::HALF_TURN),
            self::MirroredVertically => $this->flipped($stored, IMG_FLIP_VERTICAL),
            self::Transposed => $this->flipped($this->rotated($stored, self::QUARTER_TURN_CLOCKWISE), IMG_FLIP_HORIZONTAL),
            self::RotatedQuarterTurnAnticlockwise => $this->rotated($stored, self::QUARTER_TURN_CLOCKWISE),
            self::Transversed => $this->flipped($this->rotated($stored, self::QUARTER_TURN_ANTICLOCKWISE), IMG_FLIP_HORIZONTAL),
            self::RotatedQuarterTurnClockwise => $this->rotated($stored, self::QUARTER_TURN_ANTICLOCKWISE),
        };
    }

    private function rotated(GdImage $image, int $degreesAnticlockwise): GdImage
    {
        $rotated = imagerotate($image, $degreesAnticlockwise, 0);
        if (false === $rotated) {
            throw MediaProcessingException::failedToOrient($this->value);
        }

        return $rotated;
    }

    private function flipped(GdImage $image, int $mode): GdImage
    {
        imageflip($image, $mode);

        return $image;
    }
}
