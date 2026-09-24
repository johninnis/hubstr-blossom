<?php

declare(strict_types=1);

namespace Innis\Hubstr\Blossom\Tests\Support;

use GdImage;
use RuntimeException;

final class GdImageFixture
{
    public static function canvas(int $width, int $height): GdImage
    {
        $image = imagecreatetruecolor(max(1, $width), max(1, $height));

        return false === $image ? throw new RuntimeException('Failed to create test image') : $image;
    }

    public static function colour(GdImage $image, int $red, int $green, int $blue): int
    {
        $colour = imagecolorallocate($image, self::channel($red), self::channel($green), self::channel($blue));

        return false === $colour ? throw new RuntimeException('Failed to allocate test colour') : $colour;
    }

    public static function colourWithAlpha(GdImage $image, int $red, int $green, int $blue, int $alpha): int
    {
        $colour = imagecolorallocatealpha($image, self::channel($red), self::channel($green), self::channel($blue), max(0, min(127, $alpha)));

        return false === $colour ? throw new RuntimeException('Failed to allocate test colour') : $colour;
    }

    public static function quadrants(int $width, int $height): GdImage
    {
        $image = self::canvas($width, $height);
        $midX = intdiv($width, 2);
        $midY = intdiv($height, 2);

        imagefilledrectangle($image, 0, 0, $midX - 1, $midY - 1, self::colour($image, 255, 0, 0));
        imagefilledrectangle($image, $midX, 0, $width - 1, $midY - 1, self::colour($image, 0, 255, 0));
        imagefilledrectangle($image, 0, $midY, $midX - 1, $height - 1, self::colour($image, 0, 0, 255));
        imagefilledrectangle($image, $midX, $midY, $width - 1, $height - 1, self::colour($image, 255, 0, 255));

        return $image;
    }

    public static function jpegWithExifOrientation(GdImage $image, int $orientation, int $quality): string
    {
        ob_start();
        imagejpeg($image, null, $quality);
        $jpeg = (string) ob_get_clean();

        $tiff = 'II'.pack('v', 42).pack('V', 8)
            .pack('v', 1)
            .pack('v', 0x0112).pack('v', 3).pack('V', 1).pack('v', $orientation).pack('v', 0)
            .pack('V', 0);
        $app1 = "Exif\0\0".$tiff;
        $segment = "\xFF\xE1".pack('n', strlen($app1) + 2).$app1;

        return substr($jpeg, 0, 2).$segment.substr($jpeg, 2);
    }

    public static function dominantColourAt(GdImage $image, int $x, int $y): string
    {
        $rgb = imagecolorat($image, $x, $y);
        $channels = [($rgb >> 16) & 0xFF, ($rgb >> 8) & 0xFF, $rgb & 0xFF];

        return implode('', array_map(static fn (int $channel): string => $channel > 127 ? '1' : '0', $channels));
    }

    /**
     * @return int<0, 255>
     */
    private static function channel(int $value): int
    {
        return max(0, min(255, $value));
    }
}
