<?php

declare(strict_types=1);

namespace Innis\Hubstr\Blossom\Presentation\Http;

use Innis\Hubstr\Core\Domain\Enum\HttpMethod;
use Innis\Hubstr\Core\Infrastructure\Http\Route;

final class RouteTable
{
    private const string HASH_PATTERN = '/{sha256:[0-9a-f]{64}}';

    /**
     * @return list<Route>
     */
    public function blobRoutes(BlobController $blob): array
    {
        return [
            new Route(HttpMethod::Get, self::HASH_PATTERN, $blob->get(...)),
            new Route(HttpMethod::Get, self::HASH_PATTERN.'.{ext}', $blob->get(...)),
            new Route(HttpMethod::Head, self::HASH_PATTERN, $blob->head(...)),
            new Route(HttpMethod::Head, self::HASH_PATTERN.'.{ext}', $blob->head(...)),
            new Route(HttpMethod::Delete, self::HASH_PATTERN, $blob->delete(...)),
            new Route(HttpMethod::Get, '/list/{pubkey:[0-9a-f]{64}}', $blob->list(...)),
        ];
    }

    /**
     * @return list<Route>
     */
    public function ingestRoutes(BlobIngestController $ingest): array
    {
        return [
            new Route(HttpMethod::Put, '/upload', $ingest->upload(...)),
            new Route(HttpMethod::Put, '/mirror', $ingest->mirror(...)),
            new Route(HttpMethod::Put, '/media', $ingest->media(...)),
        ];
    }

    /**
     * @return list<Route>
     */
    public function preflightRoutes(BlobPreflightController $preflight): array
    {
        return [
            new Route(HttpMethod::Head, '/upload', $preflight->upload(...)),
            new Route(HttpMethod::Head, '/media', $preflight->media(...)),
        ];
    }

    /**
     * @return list<Route>
     */
    public function reportRoutes(BlobReportController $report): array
    {
        return [
            new Route(HttpMethod::Put, '/report', $report->report(...)),
        ];
    }
}
