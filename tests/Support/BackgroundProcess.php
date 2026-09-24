<?php

declare(strict_types=1);

namespace Innis\Hubstr\Blossom\Tests\Support;

use RuntimeException;

final class BackgroundProcess
{
    private const int LISTEN_ATTEMPTS = 100;
    private const int LISTEN_POLL_MICROSECONDS = 50_000;
    private const float LISTEN_PROBE_TIMEOUT_SECONDS = 0.2;

    private function __construct(private mixed $process)
    {
    }

    /**
     * @param list<string>          $command
     * @param array<string, string> $environment
     */
    public static function start(array $command, TemporaryDirectory $directory, array $environment = []): self
    {
        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['file', $directory->path('stdout.log'), 'a'],
            2 => ['file', $directory->path('stderr.log'), 'a'],
        ];

        $process = proc_open($command, $descriptors, $pipes, $directory->getPath(), [...getenv(), ...$environment]);
        if (!is_resource($process)) {
            throw new RuntimeException('Failed to start: '.implode(' ', $command));
        }

        return new self($process);
    }

    public function awaitListening(int $port): void
    {
        for ($attempt = 0; $attempt < self::LISTEN_ATTEMPTS; ++$attempt) {
            $connection = @fsockopen('127.0.0.1', $port, $errno, $errstr, self::LISTEN_PROBE_TIMEOUT_SECONDS);
            if (is_resource($connection)) {
                fclose($connection);

                return;
            }
            usleep(self::LISTEN_POLL_MICROSECONDS);
        }

        throw new RuntimeException('Process did not start listening on port '.$port);
    }

    public function stop(): void
    {
        if (!is_resource($this->process)) {
            return;
        }

        proc_terminate($this->process, SIGTERM);
        proc_close($this->process);
        $this->process = null;
    }
}
