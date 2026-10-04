<?php

declare(strict_types=1);

namespace Innis\Hubstr\Blossom\Presentation\Http;

use Amp\Http\Server\Request;
use Amp\Http\Server\Response;
use Innis\Nostr\Blossom\Application\UseCase\DeleteBlobUseCase;
use Innis\Nostr\Blossom\Application\UseCase\ListBlobsUseCase;

final readonly class BlobManagementController
{
    public function __construct(
        private DeleteBlobUseCase $deleteBlob,
        private ListBlobsUseCase $listBlobs,
        private BlobRequestReader $request,
    ) {
    }

    public function delete(Request $request): Response
    {
        return Responder::respond($this->deleteBlob->execute(
            $this->request->authHeader($request),
            $this->request->routeHash($request, 'sha256'),
        ));
    }

    public function list(Request $request): Response
    {
        $listQuery = $this->request->listQuery($request);
        if ($listQuery instanceof Response) {
            return $listQuery;
        }

        return Responder::respond($this->listBlobs->execute(
            $this->request->authHeader($request),
            $this->request->routePublicKey($request, 'pubkey'),
            $listQuery,
        ));
    }
}
