<?php

declare(strict_types=1);

namespace Innis\Hubstr\Blossom\Presentation\Http;

use Amp\Http\Server\Request;
use Amp\Http\Server\Response;
use Innis\Nostr\Blossom\Application\UseCase\MirrorBlobUseCase;
use Innis\Nostr\Blossom\Application\UseCase\OptimiseMediaUseCase;
use Innis\Nostr\Blossom\Application\UseCase\UploadBlobUseCase;

final readonly class BlobIngestController
{
    public function __construct(
        private UploadBlobUseCase $uploadBlob,
        private OptimiseMediaUseCase $optimiseMedia,
        private MirrorBlobUseCase $mirrorBlob,
        private BlobRequestReader $request,
    ) {
    }

    public function upload(Request $request): Response
    {
        $declaredHash = $this->request->headerHash($request, 'x-sha-256');
        if ($declaredHash instanceof Response) {
            return $declaredHash;
        }

        return Responder::respond($this->uploadBlob->execute(
            $this->request->authHeader($request),
            $this->request->blobSource($request),
            $declaredHash,
        ));
    }

    public function media(Request $request): Response
    {
        return Responder::respond($this->optimiseMedia->execute(
            $this->request->authHeader($request),
            $this->request->blobSource($request),
        ));
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
