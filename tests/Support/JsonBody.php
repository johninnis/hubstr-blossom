<?php

declare(strict_types=1);

namespace Innis\Hubstr\Blossom\Tests\Support;

use RuntimeException;

final readonly class JsonBody
{
    /**
     * @param array<array-key, mixed> $data
     */
    private function __construct(private array $data)
    {
    }

    public static function from(string $body): self
    {
        $decoded = json_decode($body, true);

        if (!is_array($decoded)) {
            throw new RuntimeException('Response body is not a JSON array or object');
        }

        return new self($decoded);
    }

    public function string(string $key): string
    {
        $value = $this->data[$key] ?? null;

        return is_string($value) ? $value : throw new RuntimeException(sprintf('Expected a string at "%s"', $key));
    }

    public function int(string $key): int
    {
        $value = $this->data[$key] ?? null;

        return is_int($value) ? $value : throw new RuntimeException(sprintf('Expected an int at "%s"', $key));
    }

    /**
     * @return array<array-key, mixed>
     */
    public function toArray(): array
    {
        return $this->data;
    }
}
