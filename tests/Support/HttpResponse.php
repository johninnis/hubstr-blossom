<?php

declare(strict_types=1);

namespace Innis\Hubstr\Blossom\Tests\Support;

final readonly class HttpResponse
{
    /**
     * @param array<string, string> $headers
     */
    private function __construct(
        private int $status,
        private array $headers,
        private string $body,
    ) {
    }

    /**
     * @param list<string> $headerLines the status line followed by the header lines, as PHP's stream wrapper reports them
     */
    public static function fromHeaderLines(array $headerLines, string $body): self
    {
        $headers = [];
        foreach (array_slice($headerLines, 1) as $line) {
            $position = strpos($line, ':');
            if (false === $position) {
                continue;
            }
            $headers[strtolower(trim(substr($line, 0, $position)))] = trim(substr($line, $position + 1));
        }

        return new self((int) (explode(' ', $headerLines[0] ?? '')[1] ?? 0), $headers, $body);
    }

    public function status(): int
    {
        return $this->status;
    }

    public function body(): string
    {
        return $this->body;
    }

    public function header(string $name): ?string
    {
        return $this->headers[strtolower($name)] ?? null;
    }
}
