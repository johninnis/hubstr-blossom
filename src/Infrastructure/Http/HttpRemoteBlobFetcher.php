<?php

declare(strict_types=1);

namespace Innis\Hubstr\Blossom\Infrastructure\Http;

use Amp\Http\Client\Connection\DefaultConnectionFactory;
use Amp\Http\Client\Connection\UnlimitedConnectionPool;
use Amp\Http\Client\HttpClient;
use Amp\Http\Client\HttpClientBuilder;
use Amp\Http\Client\Request;
use Amp\Http\Client\Response;
use Amp\Socket\DnsSocketConnector;
use Innis\Hubstr\Blossom\Domain\Exception\RemoteBlobFetchException;
use Innis\Hubstr\Blossom\Infrastructure\Network\PrivateAddressGuard;
use Innis\Hubstr\Blossom\Infrastructure\Network\VettingDnsResolver;
use Innis\Nostr\Blossom\Application\DTO\PendingBlob;
use Innis\Nostr\Blossom\Application\Port\RemoteBlobFetcherInterface;
use Innis\Nostr\Blossom\Domain\Failure\BlobTooLargeFailure;
use Innis\Nostr\Blossom\Domain\ValueObject\HttpUrl;
use Override;

use function Amp\Dns\dnsResolver;

final readonly class HttpRemoteBlobFetcher implements RemoteBlobFetcherInterface
{
    private const int BODY_SIZE_HEADROOM = 65536;
    private const float TIMEOUT_SECONDS = 30.0;
    private const int MAX_REDIRECTS = 5;

    private HttpClient $client;

    public function __construct(
        private int $maxBytes,
        private PrivateAddressGuard $addressGuard,
        private BlobStager $stager,
    ) {
        $connector = new DnsSocketConnector(new VettingDnsResolver(dnsResolver(), $addressGuard));

        $this->client = new HttpClientBuilder()
            ->usingPool(new UnlimitedConnectionPool(new DefaultConnectionFactory($connector)))
            ->followRedirects(0)
            ->build();
    }

    #[Override]
    public function fetch(HttpUrl $url): PendingBlob
    {
        $current = $url;

        for ($redirects = 0; $redirects <= self::MAX_REDIRECTS; ++$redirects) {
            $this->guardLiteralAddress($current);

            $response = $this->client->request($this->request($current));
            $status = $response->getStatus();

            if ($status >= 300 && $status < 400) {
                $current = $this->redirectTarget($current, $response);

                continue;
            }

            if ($status < 200 || $status >= 300) {
                throw RemoteBlobFetchException::unsuccessfulResponse($status, (string) $current);
            }

            return $this->capture($response);
        }

        throw RemoteBlobFetchException::tooManyRedirects((string) $url);
    }

    private function request(HttpUrl $url): Request
    {
        $request = new Request((string) $url, 'GET');
        $request->setTransferTimeout(self::TIMEOUT_SECONDS);
        $request->setBodySizeLimit($this->maxBytes + self::BODY_SIZE_HEADROOM);

        return $request;
    }

    private function capture(Response $response): PendingBlob
    {
        $staged = $this->stager->stage($response, $response->getBody());
        if ($staged instanceof BlobTooLargeFailure) {
            throw RemoteBlobFetchException::bodyExceedsMaximum($this->maxBytes);
        }

        return $staged;
    }

    private function redirectTarget(HttpUrl $base, Response $response): HttpUrl
    {
        $location = trim($response->getHeader('location') ?? '');
        if ('' === $location) {
            throw RemoteBlobFetchException::missingRedirectLocation((string) $base);
        }

        $target = HttpUrl::tryFromString($this->absoluteLocation($base, $location));
        if (null === $target) {
            throw RemoteBlobFetchException::unsupportedRedirectLocation($location);
        }

        return $target;
    }

    private function absoluteLocation(HttpUrl $base, string $location): string
    {
        if (1 === preg_match('#^[a-z][a-z0-9+.-]*://#i', $location)) {
            return $location;
        }

        if (str_starts_with($location, '//')) {
            return $base->getScheme().':'.$location;
        }

        if (str_starts_with($location, '/')) {
            $authority = $base->getHost().(null !== $base->getPort() ? ':'.$base->getPort() : '');

            return $base->getScheme().'://'.$authority.$location;
        }

        throw RemoteBlobFetchException::unsupportedRedirectLocation($location);
    }

    private function guardLiteralAddress(HttpUrl $url): void
    {
        $host = trim($url->getHost(), '[]');
        if (false !== filter_var($host, FILTER_VALIDATE_IP) && !$this->addressGuard->permits($host)) {
            throw RemoteBlobFetchException::refusedPrivateAddress($host);
        }
    }
}
