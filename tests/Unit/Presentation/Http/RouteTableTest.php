<?php

declare(strict_types=1);

namespace Innis\Hubstr\Blossom\Tests\Unit\Presentation\Http;

use Innis\Hubstr\Blossom\Presentation\Http\BlobIngestController;
use Innis\Hubstr\Blossom\Presentation\Http\BlobManagementController;
use Innis\Hubstr\Blossom\Presentation\Http\BlobMirrorController;
use Innis\Hubstr\Blossom\Presentation\Http\BlobPreflightController;
use Innis\Hubstr\Blossom\Presentation\Http\BlobReportController;
use Innis\Hubstr\Blossom\Presentation\Http\BlobRetrievalController;
use Innis\Hubstr\Blossom\Presentation\Http\RouteTable;
use Innis\Hubstr\Core\Infrastructure\Http\Route;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class RouteTableTest extends TestCase
{
    public function testContainsExactlyTheExpectedMethodAndPatternPairs(): void
    {
        $table = new RouteTable();

        $routes = [
            ...$table->retrievalRoutes(new ReflectionClass(BlobRetrievalController::class)->newInstanceWithoutConstructor()),
            ...$table->managementRoutes(new ReflectionClass(BlobManagementController::class)->newInstanceWithoutConstructor()),
            ...$table->ingestRoutes(new ReflectionClass(BlobIngestController::class)->newInstanceWithoutConstructor()),
            ...$table->mirrorRoutes(new ReflectionClass(BlobMirrorController::class)->newInstanceWithoutConstructor()),
            ...$table->preflightRoutes(
                new ReflectionClass(BlobPreflightController::class)->newInstanceWithoutConstructor(),
                new ReflectionClass(BlobPreflightController::class)->newInstanceWithoutConstructor(),
            ),
            ...$table->reportRoutes(new ReflectionClass(BlobReportController::class)->newInstanceWithoutConstructor()),
        ];

        $pairs = array_map(static fn (Route $route): string => $route->getMethod()->value.' '.$route->getPattern(), $routes);

        self::assertSame([
            'GET /{sha256:[0-9a-f]{64}}',
            'GET /{sha256:[0-9a-f]{64}}.{ext}',
            'HEAD /{sha256:[0-9a-f]{64}}',
            'HEAD /{sha256:[0-9a-f]{64}}.{ext}',
            'DELETE /{sha256:[0-9a-f]{64}}',
            'GET /list/{pubkey:[0-9a-f]{64}}',
            'PUT /upload',
            'PUT /media',
            'PUT /mirror',
            'HEAD /upload',
            'HEAD /media',
            'PUT /report',
        ], $pairs);
    }
}
