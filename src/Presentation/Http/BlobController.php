<?php

declare(strict_types=1);

namespace Innis\Hubstr\Blossom\Presentation\Http;

use Amp\Http\Server\Request;
use Amp\Http\Server\Response;
use Innis\Nostr\Blossom\Application\DTO\RetrievedBlob;
use Innis\Nostr\Blossom\Application\UseCase\DeleteBlobUseCase;
use Innis\Nostr\Blossom\Application\UseCase\GetBlobUseCase;
use Innis\Nostr\Blossom\Application\UseCase\ListBlobsUseCase;
use Innis\Nostr\Blossom\Domain\Failure\BlossomFailure;

final readonly class BlobController
{
    public function __construct(
        private GetBlobUseCase $getBlob,
        private DeleteBlobUseCase $deleteBlob,
        private ListBlobsUseCase $listBlobs,
        private BlobStreamResponder $blobStream,
        private BlobRequestReader $request,
    ) {
    }

    public function get(Request $request): Response
    {
        $retrieved = $this->retrieve($request);

        return $retrieved instanceof Response
            ? $retrieved
            : $this->blobStream->get($retrieved, $request->getHeader('range'), $request->getHeader('if-none-match'));
    }

    public function head(Request $request): Response
    {
        $retrieved = $this->retrieve($request);

        return $retrieved instanceof Response
            ? $retrieved
            : $this->blobStream->head($retrieved, $request->getHeader('if-none-match'));
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

    private function retrieve(Request $request): RetrievedBlob|Response
    {
        $retrieved = $this->getBlob->execute($this->request->routeHash($request, 'sha256'));

        return $retrieved instanceof BlossomFailure ? Responder::error($retrieved) : $retrieved;
    }
}
