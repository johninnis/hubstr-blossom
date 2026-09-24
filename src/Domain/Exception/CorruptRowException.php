<?php

declare(strict_types=1);

namespace Innis\Hubstr\Blossom\Domain\Exception;

use Innis\Hubstr\Core\Domain\Exception\HubstrException;

final class CorruptRowException extends HubstrException
{
    public static function invalidColumn(string $table, string $column, mixed $value): self
    {
        return new self(sprintf('Stored %s row has an invalid %s: %s', $table, $column, get_debug_type($value)));
    }

    public static function invalidHash(string $table, string $value): self
    {
        return new self(sprintf('Stored %s row has an invalid SHA-256: %s', $table, $value));
    }

    public static function invalidUrl(string $table, string $value): self
    {
        return new self(sprintf('Stored %s row has an invalid URL: %s', $table, $value));
    }

    public static function invalidEvent(string $table, int $id): self
    {
        return new self(sprintf('Stored %s row %d holds an event that no longer parses', $table, $id));
    }
}
