<?php

declare(strict_types=1);

namespace Innis\Hubstr\Blossom\Presentation\Http;

use Amp\Http\Server\Request;
use Innis\Hubstr\Blossom\Infrastructure\Http\BlobStager;
use Innis\Nostr\Blossom\Application\DTO\PendingBlob;
use Innis\Nostr\Blossom\Application\Port\PendingBlobSourceInterface;
use Innis\Nostr\Blossom\Domain\Failure\BlossomFailure;
use Override;

final readonly class StreamPendingBlobSource implements PendingBlobSourceInterface
{
    public function __construct(
        private BlobStager $stager,
        private Request $request,
    ) {
    }

    #[Override]
    public function stage(): PendingBlob|BlossomFailure
    {
        return $this->stager->stage($this->request, $this->request->getBody());
    }
}
