<?php

declare(strict_types=1);

namespace Innis\Hubstr\Blossom\Infrastructure\Worker;

use Amp\Parallel\Worker\WorkerPool;
use Innis\Nostr\Blossom\Application\Port\BlobInspectorInterface;
use Innis\Nostr\Blossom\Domain\ValueObject\IncomingBlob;
use Override;

final readonly class WorkerBlobInspector implements BlobInspectorInterface
{
    public function __construct(
        private WorkerPool $pool,
        private BlobInspectorInterface $inspector,
    ) {
    }

    #[Override]
    public function inspect(string $tempPath): IncomingBlob
    {
        return $this->run(new InspectBlobTask($this->inspector, $tempPath, false));
    }

    #[Override]
    public function inspectWithMedia(string $tempPath): IncomingBlob
    {
        return $this->run(new InspectBlobTask($this->inspector, $tempPath, true));
    }

    private function run(InspectBlobTask $task): IncomingBlob
    {
        return $this->pool->submit($task)->await();
    }
}
