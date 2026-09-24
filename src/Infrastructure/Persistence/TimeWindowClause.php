<?php

declare(strict_types=1);

namespace Innis\Hubstr\Blossom\Infrastructure\Persistence;

use Innis\Nostr\Blossom\Domain\ValueObject\ListQuery;

final readonly class TimeWindowClause
{
    public function __construct(
        private string $column,
        private ListQuery $query,
    ) {
    }

    /**
     * @return list<string>
     */
    public function getConditions(): array
    {
        return array_values(array_filter([
            null === $this->query->getSince() ? null : $this->column.' >= :since',
            null === $this->query->getUntil() ? null : $this->column.' <= :until',
        ], is_string(...)));
    }

    /**
     * @return array<string, int>
     */
    public function getParameters(): array
    {
        return array_filter([
            ':since' => $this->query->getSince()?->toInt(),
            ':until' => $this->query->getUntil()?->toInt(),
        ], is_int(...));
    }
}
