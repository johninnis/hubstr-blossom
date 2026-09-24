<?php

declare(strict_types=1);

namespace Innis\Hubstr\Blossom\Tests\Support;

final readonly class TestHttpClient
{
    public function __construct(private string $baseUrl)
    {
    }

    /**
     * @param array<string, string> $headers
     */
    public function request(string $method, string $path, array $headers = [], ?string $body = null): HttpResponse
    {
        $headerLines = [];
        foreach ($headers as $name => $value) {
            $headerLines[] = $name.': '.$value;
        }

        $context = stream_context_create([
            'http' => [
                'method' => $method,
                'header' => implode("\r\n", $headerLines),
                'content' => $body ?? '',
                'ignore_errors' => true,
                'follow_location' => 0,
                'timeout' => 5,
            ],
        ]);

        $http_response_header = [];
        $responseBody = @file_get_contents($this->baseUrl.$path, false, $context);

        return HttpResponse::fromHeaderLines($http_response_header, false === $responseBody ? '' : $responseBody);
    }
}
