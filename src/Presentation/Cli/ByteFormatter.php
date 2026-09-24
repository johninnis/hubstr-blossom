<?php

declare(strict_types=1);

namespace Innis\Hubstr\Blossom\Presentation\Cli;

final class ByteFormatter
{
    private const int KILOBYTE = 1024;
    private const int MEGABYTE = 1024 * 1024;

    private function __construct()
    {
    }

    public static function format(int $bytes): string
    {
        if ($bytes < self::KILOBYTE) {
            return $bytes.' B';
        }

        if ($bytes < self::MEGABYTE) {
            return round($bytes / self::KILOBYTE, 1).' KB';
        }

        return round($bytes / self::MEGABYTE, 1).' MB';
    }
}
