<?php

declare(strict_types=1);

namespace Innis\Hubstr\Blossom\Tests\Support;

use RuntimeException;

final readonly class RawHttpProbe
{
    private const float TIMEOUT_SECONDS = 5.0;

    public function __construct(private string $baseUrl)
    {
    }

    public function send(string $rawRequest): HttpResponse
    {
        $socket = @fsockopen((string) parse_url($this->baseUrl, PHP_URL_HOST), (int) parse_url($this->baseUrl, PHP_URL_PORT), $errno, $errstr, self::TIMEOUT_SECONDS);
        if (false === $socket) {
            throw new RuntimeException('Failed to connect: '.$errstr);
        }

        fwrite($socket, $rawRequest);
        stream_set_timeout($socket, (int) self::TIMEOUT_SECONDS);

        $head = '';
        while (!feof($socket) && !str_contains($head, "\r\n\r\n")) {
            $head .= (string) fgets($socket);
        }
        fclose($socket);

        return HttpResponse::fromHeaderLines(explode("\r\n", trim($head)), '');
    }
}
