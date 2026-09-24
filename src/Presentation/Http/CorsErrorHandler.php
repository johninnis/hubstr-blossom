<?php

declare(strict_types=1);

namespace Innis\Hubstr\Blossom\Presentation\Http;

use Amp\Http\Server\ErrorHandler;
use Amp\Http\Server\Request;
use Amp\Http\Server\Response;
use Innis\Hubstr\Blossom\Presentation\Http\Middleware\CorsHeaders;
use Override;

final readonly class CorsErrorHandler implements ErrorHandler
{
    public function __construct(
        private ErrorHandler $errorHandler,
        private CorsHeaders $cors,
    ) {
    }

    #[Override]
    public function handleError(int $status, ?string $reason = null, ?Request $request = null): Response
    {
        return $this->cors->applyTo($this->errorHandler->handleError($status, $reason, $request));
    }
}
