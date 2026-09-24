<?php

declare(strict_types=1);

namespace Innis\Hubstr\Blossom\Tests\Integration\Infrastructure\Media;

use Closure;
use GdImage;
use Innis\Hubstr\Blossom\Infrastructure\Media\ExifOrientation;
use Innis\Hubstr\Blossom\Infrastructure\Media\GdImageFormat;
use Innis\Hubstr\Blossom\Tests\Support\GdImageFixture;
use Innis\Hubstr\Blossom\Tests\Support\TemporaryDirectory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ExifOrientationTest extends TestCase
{
    private const int WIDTH = 64;
    private const int HEIGHT = 32;
    private const string RED = '100';
    private const string GREEN = '010';
    private const string BLUE = '001';
    private const string MAGENTA = '101';

    private TemporaryDirectory $directory;

    protected function setUp(): void
    {
        $this->directory = TemporaryDirectory::create();
    }

    protected function tearDown(): void
    {
        $this->directory->remove();
    }

    public function testAJpegWithoutExifReadsAsUpright(): void
    {
        $path = $this->directory->path('plain.jpg');
        imagejpeg(GdImageFixture::quadrants(self::WIDTH, self::HEIGHT), $path);

        self::assertSame(ExifOrientation::Upright, ExifOrientation::read($path));
    }

    public function testAFileThatIsNotAJpegReadsAsUpright(): void
    {
        $path = $this->directory->path('not-a-jpeg');
        file_put_contents($path, 'plain text');

        self::assertSame(ExifOrientation::Upright, ExifOrientation::read($path));
    }

    /**
     * @param Closure(GdImage): GdImage $storeAs
     */
    #[DataProvider('orientations')]
    public function testDecodingAJpegRestoresTheUprightImageWhateverItsStoredOrientation(ExifOrientation $orientation, Closure $storeAs): void
    {
        $stored = $storeAs(GdImageFixture::quadrants(self::WIDTH, self::HEIGHT));
        $path = $this->directory->path('oriented.jpg');
        file_put_contents($path, GdImageFixture::jpegWithExifOrientation($stored, $orientation->value, 100));

        self::assertSame($orientation, ExifOrientation::read($path));

        $upright = GdImageFormat::Jpeg->decode($path);

        self::assertSame([self::WIDTH, self::HEIGHT], [imagesx($upright), imagesy($upright)]);
        self::assertSame(
            [self::RED, self::GREEN, self::BLUE, self::MAGENTA],
            [
                GdImageFixture::dominantColourAt($upright, 16, 8),
                GdImageFixture::dominantColourAt($upright, 48, 8),
                GdImageFixture::dominantColourAt($upright, 16, 24),
                GdImageFixture::dominantColourAt($upright, 48, 24),
            ],
        );
    }

    /**
     * @return iterable<string, array{ExifOrientation, Closure(GdImage): GdImage}>
     */
    public static function orientations(): iterable
    {
        yield 'upright' => [ExifOrientation::Upright, static fn (GdImage $image): GdImage => $image];
        yield 'mirrored horizontally' => [ExifOrientation::MirroredHorizontally, static fn (GdImage $image): GdImage => self::flipped($image, IMG_FLIP_HORIZONTAL)];
        yield 'rotated a half turn' => [ExifOrientation::RotatedHalfTurn, static fn (GdImage $image): GdImage => self::rotated($image, 180)];
        yield 'mirrored vertically' => [ExifOrientation::MirroredVertically, static fn (GdImage $image): GdImage => self::flipped($image, IMG_FLIP_VERTICAL)];
        yield 'transposed' => [ExifOrientation::Transposed, static fn (GdImage $image): GdImage => self::flipped(self::rotated($image, -90), IMG_FLIP_HORIZONTAL)];
        yield 'rotated a quarter turn anticlockwise (a portrait phone photo)' => [ExifOrientation::RotatedQuarterTurnAnticlockwise, static fn (GdImage $image): GdImage => self::rotated($image, 90)];
        yield 'transversed' => [ExifOrientation::Transversed, static fn (GdImage $image): GdImage => self::flipped(self::rotated($image, 90), IMG_FLIP_HORIZONTAL)];
        yield 'rotated a quarter turn clockwise' => [ExifOrientation::RotatedQuarterTurnClockwise, static fn (GdImage $image): GdImage => self::rotated($image, -90)];
    }

    private static function rotated(GdImage $image, int $degreesAnticlockwise): GdImage
    {
        $rotated = imagerotate($image, $degreesAnticlockwise, 0);
        self::assertNotFalse($rotated);

        return $rotated;
    }

    private static function flipped(GdImage $image, int $mode): GdImage
    {
        imageflip($image, $mode);

        return $image;
    }
}
