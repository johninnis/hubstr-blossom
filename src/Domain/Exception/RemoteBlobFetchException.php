<?php

declare(strict_types=1);

namespace Innis\Hubstr\Blossom\Domain\Exception;

use Innis\Hubstr\Core\Domain\Exception\HubstrException;

final class RemoteBlobFetchException extends HubstrException
{
    public static function unsuccessfulResponse(int $status, string $url): self
    {
        return new self(sprintf('Remote blob request was not successful (%d): %s', $status, $url));
    }

    public static function tooManyRedirects(string $url): self
    {
        return new self(sprintf('Too many redirects while fetching remote blob: %s', $url));
    }

    public static function missingRedirectLocation(string $base): self
    {
        return new self(sprintf('Remote blob redirect is missing a location: %s', $base));
    }

    public static function unsupportedRedirectLocation(string $location): self
    {
        return new self(sprintf('Remote blob redirected to an unsupported location: %s', $location));
    }

    public static function bodyExceedsMaximum(int $maxBytes): self
    {
        return new self(sprintf('Remote blob exceeds the maximum size of %d bytes', $maxBytes));
    }

    public static function refusedPrivateAddress(string $host): self
    {
        return new self(sprintf('Refusing to fetch a private or reserved address: %s', $host));
    }
}
