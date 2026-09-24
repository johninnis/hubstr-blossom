<?php

declare(strict_types=1);

namespace Innis\Hubstr\Blossom\Presentation\Http\Middleware;

use Amp\Http\Server\Middleware;
use Amp\Http\Server\Request;
use Amp\Http\Server\RequestHandler;
use Amp\Http\Server\Response;
use Override;

final readonly class CorsMiddleware implements Middleware
{
    public function __construct(private CorsHeaders $cors)
    {
    }

    #[Override]
    public function handleRequest(Request $request, RequestHandler $requestHandler): Response
    {
        return $this->cors->applyTo($requestHandler->handleRequest($request));
    }
}
