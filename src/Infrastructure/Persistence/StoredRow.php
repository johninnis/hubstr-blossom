<?php

declare(strict_types=1);

namespace Innis\Hubstr\Blossom\Infrastructure\Persistence;

use Innis\Hubstr\Blossom\Domain\Exception\CorruptRowException;

final readonly class StoredRow
{
    /**
     * @param array<array-key, mixed> $columns
     */
    public function __construct(
        private string $table,
        private array $columns,
    ) {
    }

    public function string(string $column): string
    {
        $value = $this->columns[$column] ?? null;

        return is_string($value) ? $value : throw CorruptRowException::invalidColumn($this->table, $column, $value);
    }

    public function optionalString(string $column): ?string
    {
        $value = $this->columns[$column] ?? null;

        return is_string($value) && '' !== $value ? $value : null;
    }

    public function int(string $column): int
    {
        $value = $this->columns[$column] ?? null;

        return is_int($value) ? $value : throw CorruptRowException::invalidColumn($this->table, $column, $value);
    }
}
