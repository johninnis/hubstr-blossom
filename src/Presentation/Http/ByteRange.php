<?php

declare(strict_types=1);

namespace Innis\Hubstr\Blossom\Presentation\Http;

final readonly class ByteRange
{
    private function __construct(
        private int $start,
        private int $length,
        private bool $isSatisfiable,
    ) {
    }

    public function getStart(): int
    {
        return $this->start;
    }

    public function getLength(): int
    {
        return $this->length;
    }

    public function isSatisfiable(): bool
    {
        return $this->isSatisfiable;
    }

    public static function satisfiable(int $start, int $length): self
    {
        return new self($start, $length, true);
    }

    public static function unsatisfiable(): self
    {
        return new self(0, 0, false);
    }

    public static function parse(?string $header, int $size): ?self
    {
        if (null === $header || !str_starts_with($header, 'bytes=')) {
            return null;
        }

        $spec = substr($header, strlen('bytes='));
        if (str_contains($spec, ',')) {
            return null;
        }

        $dash = strpos($spec, '-');
        if (false === $dash) {
            return null;
        }

        $startRaw = substr($spec, 0, $dash);
        $endRaw = substr($spec, $dash + 1);

        if ('' === $startRaw) {
            return self::fromSuffix($endRaw, $size);
        }

        if (!ctype_digit($startRaw)) {
            return null;
        }

        return self::fromOffset((int) $startRaw, $endRaw, $size);
    }

    private static function fromSuffix(string $suffixRaw, int $size): ?self
    {
        if ('' === $suffixRaw || !ctype_digit($suffixRaw)) {
            return null;
        }

        $suffix = (int) $suffixRaw;
        if (0 === $suffix) {
            return self::unsatisfiable();
        }

        $length = min($suffix, $size);

        return self::satisfiable($size - $length, $length);
    }

    private static function fromOffset(int $start, string $endRaw, int $size): ?self
    {
        if ('' !== $endRaw && !ctype_digit($endRaw)) {
            return null;
        }

        if ($start >= $size) {
            return self::unsatisfiable();
        }

        if ('' === $endRaw) {
            return self::satisfiable($start, $size - $start);
        }

        $end = min((int) $endRaw, $size - 1);
        if ($end < $start) {
            return self::unsatisfiable();
        }

        return self::satisfiable($start, $end - $start + 1);
    }
}
