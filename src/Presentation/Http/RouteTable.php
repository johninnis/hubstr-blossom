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
    public function retrievalRoutes(BlobRetrievalController $retrieval): array
    {
        return [
            new Route(HttpMethod::Get, self::HASH_PATTERN, $retrieval->get(...)),
            new Route(HttpMethod::Get, self::HASH_PATTERN.'.{ext}', $retrieval->get(...)),
            new Route(HttpMethod::Head, self::HASH_PATTERN, $retrieval->head(...)),
            new Route(HttpMethod::Head, self::HASH_PATTERN.'.{ext}', $retrieval->head(...)),
        ];
    }

    /**
     * @return list<Route>
     */
    public function managementRoutes(BlobManagementController $management): array
    {
        return [
            new Route(HttpMethod::Delete, self::HASH_PATTERN, $management->delete(...)),
            new Route(HttpMethod::Get, '/list/{pubkey:[0-9a-f]{64}}', $management->list(...)),
        ];
    }

    /**
     * @return list<Route>
     */
    public function ingestRoutes(BlobIngestController $ingest): array
    {
        return [
            new Route(HttpMethod::Put, '/upload', $ingest->upload(...)),
            new Route(HttpMethod::Put, '/media', $ingest->media(...)),
        ];
    }

    /**
     * @return list<Route>
     */
    public function mirrorRoutes(BlobMirrorController $mirror): array
    {
        return [
            new Route(HttpMethod::Put, '/mirror', $mirror->mirror(...)),
        ];
    }

    /**
     * @return list<Route>
     */
    public function preflightRoutes(BlobPreflightController $uploadPreflight, BlobPreflightController $mediaPreflight): array
    {
        return [
            new Route(HttpMethod::Head, '/upload', $uploadPreflight->preflight(...)),
            new Route(HttpMethod::Head, '/media', $mediaPreflight->preflight(...)),
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
