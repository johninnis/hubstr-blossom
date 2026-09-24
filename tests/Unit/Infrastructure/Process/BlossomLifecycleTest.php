<?php

declare(strict_types=1);

namespace Innis\Hubstr\Blossom\Tests\Unit\Infrastructure\Process;

use Amp\Parallel\Worker\WorkerPool;
use Innis\Hubstr\Blossom\Infrastructure\Process\BlossomLifecycle;
use PHPUnit\Framework\TestCase;

final class BlossomLifecycleTest extends TestCase
{
    public function testStopKillsTheWorkerPoolRatherThanShuttingItDown(): void
    {
        $workers = $this->createMock(WorkerPool::class);
        $workers->expects(self::once())->method('kill');
        $workers->expects(self::never())->method('shutdown');

        new BlossomLifecycle($workers)->stop();
    }

    public function testStartAndDrainTouchNoPool(): void
    {
        $workers = $this->createMock(WorkerPool::class);
        $workers->expects(self::never())->method('kill');
        $workers->expects(self::never())->method('shutdown');

        $lifecycle = new BlossomLifecycle($workers);
        $lifecycle->start();
        $lifecycle->drain();
    }
}
