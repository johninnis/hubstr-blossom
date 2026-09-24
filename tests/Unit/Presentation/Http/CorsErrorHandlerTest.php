<?php

declare(strict_types=1);

namespace Innis\Hubstr\Blossom\Tests\Unit\Presentation\Http;

use Amp\Http\HttpStatus;
use Amp\Http\Server\ErrorHandler;
use Amp\Http\Server\Response;
use Innis\Hubstr\Blossom\Presentation\Http\CorsErrorHandler;
use Innis\Hubstr\Blossom\Presentation\Http\Middleware\CorsHeaders;
use PHPUnit\Framework\TestCase;

final class CorsErrorHandlerTest extends TestCase
{
    public function testAppliesCorsHeadersToTheWrappedErrorResponse(): void
    {
        $inner = self::createStub(ErrorHandler::class);
        $inner->method('handleError')->willReturn(new Response(HttpStatus::INTERNAL_SERVER_ERROR));

        $response = new CorsErrorHandler($inner, new CorsHeaders())->handleError(HttpStatus::INTERNAL_SERVER_ERROR);

        self::assertSame('*', $response->getHeader('access-control-allow-origin'));
        self::assertSame('Authorization, Content-Type, X-SHA-256, X-Content-Length, X-Content-Type', $response->getHeader('access-control-allow-headers'));
        self::assertNotNull($response->getHeader('access-control-expose-headers'));
    }

    public function testPreservesTheWrappedStatus(): void
    {
        $inner = self::createStub(ErrorHandler::class);
        $inner->method('handleError')->willReturn(new Response(HttpStatus::NOT_FOUND));

        $response = new CorsErrorHandler($inner, new CorsHeaders())->handleError(HttpStatus::NOT_FOUND);

        self::assertSame(HttpStatus::NOT_FOUND, $response->getStatus());
    }
}
