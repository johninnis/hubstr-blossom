<?php

declare(strict_types=1);

namespace Innis\Hubstr\Blossom\Infrastructure\Http;

final class ContentLengthHeader
{
    private function __construct()
    {
    }

    public static function parse(?string $value): ?int
    {
        return null !== $value && ctype_digit($value) ? (int) $value : null;
    }
}
