<?php

declare(strict_types=1);

namespace Innis\Hubstr\Blossom\Presentation\Http;

use Amp\Http\Server\Request;
use Amp\Http\Server\Response;
use Innis\Nostr\Blossom\Application\UseCase\MirrorBlobUseCase;

final readonly class BlobMirrorController
{
    public function __construct(
        private MirrorBlobUseCase $mirrorBlob,
        private BlobRequestReader $request,
    ) {
    }

    public function mirror(Request $request): Response
    {
        $url = $this->request->mirrorUrl($request);
        if ($url instanceof Response) {
            return $url;
        }

        return Responder::respond($this->mirrorBlob->execute($this->request->authHeader($request), $url));
    }
}
