<?php

declare(strict_types=1);

namespace Innis\Hubstr\Blossom\Presentation\Cli;

final readonly class ListOptionFailure
{
    public function __construct(
        private string $option,
        private string $value,
    ) {
    }

    public function getMessage(): string
    {
        return sprintf('Invalid --%s: %s', $this->option, '' === $this->value ? 'expected a single value' : $this->value);
    }
}
