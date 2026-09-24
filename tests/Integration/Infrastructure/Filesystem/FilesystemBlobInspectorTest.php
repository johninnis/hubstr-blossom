<?php

declare(strict_types=1);

namespace Innis\Hubstr\Blossom\Tests\Integration\Infrastructure\Filesystem;

use Innis\Hubstr\Blossom\Infrastructure\Filesystem\FilesystemBlobInspector;
use Innis\Hubstr\Blossom\Infrastructure\Filesystem\TempFileFactory;
use Innis\Hubstr\Blossom\Infrastructure\Media\ImagePixelBudget;
use Innis\Hubstr\Blossom\Tests\Support\GdImageFixture;
use Innis\Hubstr\Blossom\Tests\Support\TemporaryDirectory;
use PHPUnit\Framework\TestCase;

final class FilesystemBlobInspectorTest extends TestCase
{
    private const int IMAGE_SIDE = 64;
    private const int BUDGET_BELOW_IMAGE = 100;
    private const int BUDGET_ABOVE_IMAGE = 1_000_000;

    private TemporaryDirectory $directory;

    protected function setUp(): void
    {
        $this->directory = TemporaryDirectory::create();
    }

    protected function tearDown(): void
    {
        $this->directory->remove();
    }

    public function testReportsDimensionsAndBlurhashForAnImageWithinBudget(): void
    {
        $path = $this->writePng();

        $media = new FilesystemBlobInspector(new ImagePixelBudget(self::BUDGET_ABOVE_IMAGE))->inspectWithMedia($path)->getMedia();

        self::assertNotNull($media);
        self::assertSame('64x64', $media->getDimensions());
        self::assertNotNull($media->getBlurhash());
    }

    public function testSkipsTheBlurhashDecodeForAnImageBeyondBudgetButKeepsDimensions(): void
    {
        $path = $this->writePng();

        $media = new FilesystemBlobInspector(new ImagePixelBudget(self::BUDGET_BELOW_IMAGE))->inspectWithMedia($path)->getMedia();

        self::assertNotNull($media);
        self::assertSame('64x64', $media->getDimensions());
        self::assertNull($media->getBlurhash(), 'The pixel decode must be skipped for an over-budget image');
    }

    public function testPlainInspectCarriesNoMedia(): void
    {
        $path = $this->writePng();

        $blob = new FilesystemBlobInspector(new ImagePixelBudget(self::BUDGET_ABOVE_IMAGE))->inspect($path);

        self::assertNull($blob->getMedia());
        self::assertGreaterThan(0, $blob->getSize());
    }

    private function writePng(): string
    {
        $image = GdImageFixture::canvas(self::IMAGE_SIDE, self::IMAGE_SIDE);
        imagefilledrectangle($image, 0, 0, self::IMAGE_SIDE - 1, self::IMAGE_SIDE - 1, GdImageFixture::colour($image, 30, 120, 200));

        $path = new TempFileFactory($this->directory->getPath())->create('blossom_inspector_test_');
        imagepng($image, $path, 0);

        return $path;
    }
}
