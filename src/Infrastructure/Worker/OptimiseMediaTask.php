<?php

declare(strict_types=1);

namespace Innis\Hubstr\Blossom\Infrastructure\Worker;

use Amp\Cancellation;
use Amp\Parallel\Worker\Task;
use Amp\Sync\Channel;
use Innis\Nostr\Blossom\Application\DTO\PendingBlob;
use Innis\Nostr\Blossom\Application\Port\MediaOptimiserInterface;
use Override;

/**
 * @implements Task<PendingBlob, mixed, mixed>
 */
final readonly class OptimiseMediaTask implements Task
{
    public function __construct(
        private PendingBlob $source,
        private MediaOptimiserInterface $optimiser,
    ) {
    }

    #[Override]
    public function run(Channel $channel, Cancellation $cancellation): PendingBlob
    {
        return $this->optimiser->optimise($this->source);
    }
}
