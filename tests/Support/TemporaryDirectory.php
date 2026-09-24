<?php

declare(strict_types=1);

namespace Innis\Hubstr\Blossom\Tests\Support;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use SplFileInfo;

final readonly class TemporaryDirectory
{
    private const int NAME_BYTES = 8;

    private function __construct(
        private string $path,
    ) {
    }

    public static function create(): self
    {
        $path = sprintf('%s/hubstr-blossom-test-%s', sys_get_temp_dir(), bin2hex(random_bytes(self::NAME_BYTES)));

        if (!mkdir($path, 0o700)) {
            throw new RuntimeException(sprintf('could not create the temporary directory %s', $path));
        }

        return new self($path);
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function path(string $relative): string
    {
        return $this->path.'/'.$relative;
    }

    public function remove(): void
    {
        $entries = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->path, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($entries as $entry) {
            if (!$entry instanceof SplFileInfo) {
                continue;
            }

            $entry->isDir() && !$entry->isLink() ? rmdir($entry->getPathname()) : unlink($entry->getPathname());
        }

        rmdir($this->path);
    }
}
