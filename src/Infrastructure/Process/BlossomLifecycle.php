<?php

declare(strict_types=1);

namespace Innis\Hubstr\Blossom\Infrastructure\Process;

use Amp\Parallel\Worker\WorkerPool;
use Innis\Hubstr\Core\Application\Port\LifecycleInterface;
use Override;

final readonly class BlossomLifecycle implements LifecycleInterface
{
    public function __construct(private WorkerPool $workers)
    {
    }

    #[Override]
    public function start(): void
    {
    }

    #[Override]
    public function drain(): void
    {
    }

    // Deliberate: the pool is killed, never shut down gracefully — see ADR-0008
    #[Override]
    public function stop(): void
    {
        $this->workers->kill();
    }
}
