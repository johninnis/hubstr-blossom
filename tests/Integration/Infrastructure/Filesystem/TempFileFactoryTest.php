<?php

declare(strict_types=1);

namespace Innis\Hubstr\Blossom\Tests\Integration\Infrastructure\Filesystem;

use Innis\Hubstr\Blossom\Infrastructure\Filesystem\TempFileFactory;
use Innis\Hubstr\Blossom\Tests\Support\TemporaryDirectory;
use PHPUnit\Framework\TestCase;

final class TempFileFactoryTest extends TestCase
{
    private TemporaryDirectory $parent;

    private string $directory;

    protected function setUp(): void
    {
        $this->parent = TemporaryDirectory::create();
        $this->directory = $this->parent->path('tmp');
    }

    protected function tearDown(): void
    {
        $this->parent->remove();
    }

    public function testCreatesTheDirectoryOnDemand(): void
    {
        self::assertDirectoryDoesNotExist($this->directory);

        new TempFileFactory($this->directory)->create('blossom_test_');

        self::assertDirectoryExists($this->directory);
    }

    public function testCreatesAnEmptyFileInsideTheConfiguredDirectory(): void
    {
        $path = new TempFileFactory($this->directory)->create('blossom_test_');

        self::assertFileExists($path);
        self::assertStringStartsWith($this->directory.'/', $path);
        self::assertSame('', (string) file_get_contents($path));
    }

    public function testEachCallReturnsADistinctPath(): void
    {
        $factory = new TempFileFactory($this->directory);

        self::assertNotSame($factory->create('blossom_test_'), $factory->create('blossom_test_'));
    }
}
