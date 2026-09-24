<?php

declare(strict_types=1);

namespace Innis\Hubstr\Blossom\Infrastructure\Worker;

use Amp\Parallel\Worker\WorkerPool;
use Innis\Nostr\Blossom\Application\DTO\PendingBlob;
use Innis\Nostr\Blossom\Application\Port\MediaOptimiserInterface;
use Innis\Nostr\Blossom\Domain\ValueObject\MimeType;
use Override;

final readonly class WorkerMediaOptimiser implements MediaOptimiserInterface
{
    public function __construct(
        private WorkerPool $pool,
        private MediaOptimiserInterface $optimiser,
    ) {
    }

    #[Override]
    public function supports(MimeType $mimeType): bool
    {
        return $this->optimiser->supports($mimeType);
    }

    #[Override]
    public function optimise(PendingBlob $source): PendingBlob
    {
        return $this->pool->submit(new OptimiseMediaTask($source, $this->optimiser))->await();
    }
}
