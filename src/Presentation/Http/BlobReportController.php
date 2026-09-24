<?php

declare(strict_types=1);

namespace Innis\Hubstr\Blossom\Presentation\Http;

use Amp\Http\Server\Request;
use Amp\Http\Server\Response;
use Innis\Nostr\Blossom\Application\UseCase\ReportBlobUseCase;

final readonly class BlobReportController
{
    private const int REPORT_BODY_LIMIT = 65536;

    public function __construct(
        private ReportBlobUseCase $reportBlob,
        private BlobRequestReader $request,
    ) {
    }

    public function report(Request $request): Response
    {
        $body = $this->request->bufferBody($request, self::REPORT_BODY_LIMIT);
        if ($body instanceof Response) {
            return $body;
        }

        return Responder::respond($this->reportBlob->execute($body));
    }
}
