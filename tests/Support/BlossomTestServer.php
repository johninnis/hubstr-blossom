<?php

declare(strict_types=1);

namespace Innis\Hubstr\Blossom\Tests\Support;

use Innis\Nostr\Core\Domain\Service\SignatureServiceInterface;
use Innis\Nostr\Core\Domain\ValueObject\Identity\KeyPair;
use Innis\Nostr\Core\Infrastructure\Crypto\Secp256k1Signer;

final class BlossomTestServer
{
    private static ?SignatureServiceInterface $signer = null;

    private function __construct(
        private readonly BlossomDaemon $daemon,
        private readonly KeyPair $owner,
        private readonly KeyPair $secondTenant,
    ) {
    }

    public static function boot(): self
    {
        $owner = KeyPair::generate(self::sharedSigner());
        $secondTenant = KeyPair::generate(self::sharedSigner());

        $daemon = BlossomDaemon::start([
            $owner->getPublicKey()->toHex(),
            $secondTenant->getPublicKey()->toHex(),
        ]);

        return new self($daemon, $owner, $secondTenant);
    }

    public function stop(): void
    {
        $this->daemon->stop();
    }

    /**
     * @param array<string, string> $headers
     */
    public function request(string $method, string $path, array $headers = [], ?string $body = null): HttpResponse
    {
        return new TestHttpClient($this->daemon->baseUrl())->request($method, $path, $headers, $body);
    }

    public function probe(string $rawRequest): HttpResponse
    {
        return new RawHttpProbe($this->daemon->baseUrl())->send($rawRequest);
    }

    public function seed(string $content, string $type): string
    {
        return new BlobSeeder($this->daemon)->seed($content, $type, $this->owner->getPublicKey());
    }

    public function authHeader(string $verb, ?string $hash = null): string
    {
        return $this->authHeaderFor($this->owner, $verb, $hash);
    }

    public function authHeaderFor(KeyPair $keyPair, string $verb, ?string $hash = null): string
    {
        return new BlossomAuthHeader(self::sharedSigner())->for($keyPair, $verb, $hash);
    }

    public function baseUrl(): string
    {
        return $this->daemon->baseUrl();
    }

    public function owner(): KeyPair
    {
        return $this->owner;
    }

    public function secondTenant(): KeyPair
    {
        return $this->secondTenant;
    }

    public function signer(): SignatureServiceInterface
    {
        return self::sharedSigner();
    }

    public function ownerPubkeyHex(): string
    {
        return $this->owner->getPublicKey()->toHex();
    }

    public function secondTenantPubkeyHex(): string
    {
        return $this->secondTenant->getPublicKey()->toHex();
    }

    private static function sharedSigner(): SignatureServiceInterface
    {
        return self::$signer ??= Secp256k1Signer::create();
    }
}
