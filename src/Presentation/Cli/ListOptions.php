<?php

declare(strict_types=1);

namespace Innis\Hubstr\Blossom\Presentation\Cli;

use Innis\Nostr\Blossom\Domain\ValueObject\ListQuery;

final readonly class ListOptions
{
    public const array LONG_OPTIONS = ['limit:'];

    private const string LIMIT = 'limit';

    /**
     * @param array<array-key, mixed> $options
     */
    public static function toQuery(array $options): ListQuery|ListOptionFailure
    {
        if (!array_key_exists(self::LIMIT, $options)) {
            return new ListQuery();
        }

        $raw = $options[self::LIMIT];

        if (!is_string($raw)) {
            return new ListOptionFailure(self::LIMIT, '');
        }

        return ListQuery::tryFromQueryString(http_build_query([self::LIMIT => $raw]))
            ?? new ListOptionFailure(self::LIMIT, $raw);
    }
}
