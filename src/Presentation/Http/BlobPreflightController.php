<?php

declare(strict_types=1);

namespace Innis\Hubstr\Blossom\Presentation\Http;

use Amp\Http\HttpStatus;
use Amp\Http\Server\Request;
use Amp\Http\Server\Response;
use Closure;
use Innis\Nostr\Blossom\Domain\Failure\BlossomFailure;
use Innis\Nostr\Blossom\Domain\ValueObject\DeclaredBlob;
use Innis\Nostr\Blossom\Domain\ValueObject\MimeType;
use Innis\Nostr\Blossom\Domain\ValueObject\UploadConstraints;

final readonly class BlobPreflightController
{
    /**
     * @param Closure(string, DeclaredBlob): ?BlossomFailure $check
     */
    public function __construct(
        private Closure $check,
        private UploadConstraints $constraints,
        private BlobRequestReader $request,
    ) {
    }

    public function preflight(Request $request): Response
    {
        $declared = $this->request->declaredBlob($request);
        if ($declared instanceof Response) {
            return $declared;
        }

        if (null === $declared) {
            return $this->requirements();
        }

        return Responder::respond(($this->check)($this->request->authHeader($request), $declared));
    }

    private function requirements(): Response
    {
        return new Response(HttpStatus::OK, [
            'x-max-upload-size' => (string) $this->constraints->getMaxUploadBytes(),
            'x-content-types' => implode(', ', array_map(
                static fn (MimeType $type): string => (string) $type,
                $this->constraints->getAllowedMimeTypes()->toArray(),
            )),
        ]);
    }
}
