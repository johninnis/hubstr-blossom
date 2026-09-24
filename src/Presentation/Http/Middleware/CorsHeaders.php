<?php

declare(strict_types=1);

namespace Innis\Hubstr\Blossom\Presentation\Http\Middleware;

use Amp\Http\Server\Response;

final readonly class CorsHeaders
{
    private const array HEADERS = [
        'access-control-allow-origin' => '*',
        'access-control-allow-headers' => 'Authorization, Content-Type, X-SHA-256, X-Content-Length, X-Content-Type',
        'access-control-expose-headers' => 'Content-Range, Content-Length, ETag, X-Reason, X-Max-Upload-Size, X-Content-Types',
        'access-control-max-age' => '86400',
    ];

    public function applyTo(Response $response): Response
    {
        foreach (self::HEADERS as $name => $value) {
            $response->setHeader($name, $value);
        }

        return $response;
    }
}
