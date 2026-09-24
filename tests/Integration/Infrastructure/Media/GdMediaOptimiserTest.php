<?php

declare(strict_types=1);

namespace Innis\Hubstr\Blossom\Tests\Integration\Infrastructure\Media;

use Innis\Hubstr\Blossom\Infrastructure\Filesystem\TempFileFactory;
use Innis\Hubstr\Blossom\Infrastructure\Media\GdMediaOptimiser;
use Innis\Hubstr\Blossom\Infrastructure\Media\ImagePixelBudget;
use Innis\Hubstr\Blossom\Tests\Support\GdImageFixture;
use Innis\Hubstr\Blossom\Tests\Support\TemporaryDirectory;
use Innis\Nostr\Blossom\Application\DTO\PendingBlob;
use Innis\Nostr\Blossom\Domain\ValueObject\MimeType;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class GdMediaOptimiserTest extends TestCase
{
    private const int IMAGE_SIDE = 256;
    private const int BUDGET_BELOW_IMAGE = 100;
    private const int BUDGET_ABOVE_IMAGE = 50_000_000;

    private TemporaryDirectory $directory;

    private TempFileFactory $tempFiles;

    protected function setUp(): void
    {
        $this->directory = TemporaryDirectory::create();
        $this->tempFiles = new TempFileFactory($this->directory->getPath());
    }

    protected function tearDown(): void
    {
        $this->directory->remove();
    }

    #[DataProvider('supportedTypes')]
    public function testSupportsKnownImageFormats(string $mimeType): void
    {
        self::assertTrue($this->optimiser(self::BUDGET_ABOVE_IMAGE)->supports(MimeType::fromString($mimeType)));
    }

    public function testDoesNotSupportNonImageFormats(): void
    {
        $optimiser = $this->optimiser(self::BUDGET_ABOVE_IMAGE);

        self::assertFalse($optimiser->supports(MimeType::fromString('video/mp4')));
        self::assertFalse($optimiser->supports(MimeType::fromString('application/pdf')));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function supportedTypes(): array
    {
        return [
            'jpeg' => ['image/jpeg'],
            'png' => ['image/png'],
            'webp' => ['image/webp'],
            'gif' => ['image/gif'],
        ];
    }

    public function testOptimisingATransparentPngRetainsItsAlphaChannel(): void
    {
        $sourcePath = $this->writeTransparentPng();

        $result = $this->optimiser(self::BUDGET_ABOVE_IMAGE)->optimise(new PendingBlob($sourcePath, MimeType::fromString('image/png')));

        self::assertNotSame($sourcePath, $result->getPath(), 'Expected the re-encoded PNG to be smaller and replace the source');

        $optimised = imagecreatefrompng($result->getPath());
        self::assertNotFalse($optimised);

        $alpha = (imagecolorat($optimised, 0, 0) >> 24) & 0x7F;

        self::assertSame(127, $alpha, 'The fully transparent corner pixel must remain transparent after optimisation');
    }

    public function testAPortraitJpegIsStoredUprightRatherThanSideways(): void
    {
        $sourcePath = $this->tempFiles->create('blossom_optimiser_test_');
        $portrait = GdImageFixture::quadrants(128, 256);
        $storedSideways = imagerotate($portrait, 90, 0);
        self::assertNotFalse($storedSideways);
        file_put_contents($sourcePath, GdImageFixture::jpegWithExifOrientation($storedSideways, 6, 100));

        $result = $this->optimiser(self::BUDGET_ABOVE_IMAGE)->optimise(new PendingBlob($sourcePath, MimeType::fromString('image/jpeg')));

        self::assertNotSame($sourcePath, $result->getPath(), 'Expected the re-encoded JPEG to be smaller and replace the source');
        $dimensions = getimagesize($result->getPath());
        self::assertNotFalse($dimensions);
        self::assertSame([128, 256], [$dimensions[0], $dimensions[1]]);
    }

    public function testAnimatedWebpIsPassedThroughUntouched(): void
    {
        $sourcePath = $this->writeAnimatedWebpHeader();
        $source = new PendingBlob($sourcePath, MimeType::fromString('image/webp'));

        $result = $this->optimiser(self::BUDGET_ABOVE_IMAGE)->optimise($source);

        self::assertSame($sourcePath, $result->getPath(), 'Animated WebP must not be flattened by the optimiser');
    }

    public function testAnimatedGifIsPassedThroughUntouched(): void
    {
        $sourcePath = $this->tempFiles->create('blossom_optimiser_test_');
        file_put_contents($sourcePath, "GIF89a\x01\x00\x01\x00\x00\x00\x00\x00\x21\xF9\x04\x00\x00\x00\x00\x00\x00\x21\xF9\x04\x00\x00\x00\x00\x00\x3B");

        $result = $this->optimiser(self::BUDGET_ABOVE_IMAGE)->optimise(new PendingBlob($sourcePath, MimeType::fromString('image/gif')));

        self::assertSame($sourcePath, $result->getPath(), 'Animated GIF must not be flattened by the optimiser');
    }

    public function testImageBeyondThePixelBudgetIsPassedThroughUndecoded(): void
    {
        $sourcePath = $this->writeTransparentPng();

        $result = $this->optimiser(self::BUDGET_BELOW_IMAGE)->optimise(new PendingBlob($sourcePath, MimeType::fromString('image/png')));

        self::assertSame($sourcePath, $result->getPath(), 'An image over the pixel budget must be passed through without being decoded');
    }

    private function optimiser(int $pixelBudget): GdMediaOptimiser
    {
        return new GdMediaOptimiser($this->tempFiles, new ImagePixelBudget($pixelBudget));
    }

    private function writeTransparentPng(): string
    {
        $image = GdImageFixture::canvas(self::IMAGE_SIDE, self::IMAGE_SIDE);
        imagealphablending($image, false);
        imagesavealpha($image, true);

        imagefilledrectangle($image, 0, 0, self::IMAGE_SIDE - 1, self::IMAGE_SIDE - 1, GdImageFixture::colourWithAlpha($image, 0, 0, 0, 127));
        imagefilledrectangle($image, 64, 64, 192, 192, GdImageFixture::colourWithAlpha($image, 200, 120, 40, 0));

        $path = $this->tempFiles->create('blossom_optimiser_test_');
        imagepng($image, $path, 0);

        return $path;
    }

    private function writeAnimatedWebpHeader(): string
    {
        $payload = 'RIFF'.pack('V', 18).'WEBPVP8X'.pack('V', 10).chr(0x02).str_repeat("\x00", 9);

        $path = $this->tempFiles->create('blossom_optimiser_test_');
        file_put_contents($path, $payload);

        return $path;
    }
}
