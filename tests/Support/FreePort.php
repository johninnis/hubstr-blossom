<?php

declare(strict_types=1);

namespace Innis\Hubstr\Blossom\Tests\Support;

use RuntimeException;

final class FreePort
{
    private function __construct()
    {
    }

    public static function reserve(): int
    {
        $socket = stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);
        if (false === $socket) {
            throw new RuntimeException('Failed to reserve a free port: '.$errstr);
        }

        $name = (string) stream_socket_get_name($socket, false);
        fclose($socket);

        return (int) substr($name, strrpos($name, ':') + 1);
    }
}
