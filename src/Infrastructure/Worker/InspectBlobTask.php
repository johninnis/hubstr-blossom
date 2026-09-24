<?php

declare(strict_types=1);

namespace Innis\Hubstr\Blossom\Infrastructure\Worker;

use Amp\Cancellation;
use Amp\Parallel\Worker\Task;
use Amp\Sync\Channel;
use Innis\Nostr\Blossom\Application\Port\BlobInspectorInterface;
use Innis\Nostr\Blossom\Domain\ValueObject\IncomingBlob;
use Override;

/**
 * @implements Task<IncomingBlob, mixed, mixed>
 */
final readonly class InspectBlobTask implements Task
{
    public function __construct(
        private BlobInspectorInterface $inspector,
        private string $tempPath,
        private bool $withMedia,
    ) {
    }

    #[Override]
    public function run(Channel $channel, Cancellation $cancellation): IncomingBlob
    {
        return $this->withMedia
            ? $this->inspector->inspectWithMedia($this->tempPath)
            : $this->inspector->inspect($this->tempPath);
    }
}
