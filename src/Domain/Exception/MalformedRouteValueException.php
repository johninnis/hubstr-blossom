<?php

declare(strict_types=1);

namespace Innis\Hubstr\Blossom\Domain\Exception;

use Innis\Hubstr\Core\Domain\Exception\HubstrException;

final class MalformedRouteValueException extends HubstrException
{
    public static function hash(string $value): self
    {
        return new self(sprintf('Route matched but yielded an invalid blob hash: %s', $value));
    }

    public static function publicKey(string $value): self
    {
        return new self(sprintf('Route matched but yielded an invalid public key: %s', $value));
    }
}
