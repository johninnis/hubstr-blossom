<?php

declare(strict_types=1);

namespace Innis\Hubstr\Blossom\Infrastructure\Network;

use InvalidArgumentException;

final readonly class PrivateAddressGuard
{
    private const string IPV4_MAPPED_IPV6_PREFIX = "\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\xff\xff";

    /** @var array<string, int> */
    private const array BLOCKED_RANGES = [
        '0.0.0.0' => 8,
        '10.0.0.0' => 8,
        '100.64.0.0' => 10,
        '127.0.0.0' => 8,
        '169.254.0.0' => 16,
        '172.16.0.0' => 12,
        '192.0.0.0' => 24,
        '192.0.2.0' => 24,
        '192.88.99.0' => 24,
        '192.168.0.0' => 16,
        '198.18.0.0' => 15,
        '198.51.100.0' => 24,
        '203.0.113.0' => 24,
        '224.0.0.0' => 4,
        '240.0.0.0' => 4,
        '::' => 128,
        '::1' => 128,
        '64:ff9b::' => 96,
        '64:ff9b:1::' => 48,
        '100::' => 64,
        '2001::' => 32,
        '2001:db8::' => 32,
        '2002::' => 16,
        'fc00::' => 7,
        'fe80::' => 10,
        'fec0::' => 10,
        'ff00::' => 8,
    ];

    /** @var array<string, int> */
    private array $blockedRanges;

    public function __construct(private bool $allowPrivateHosts = false)
    {
        $packedRanges = [];
        foreach (self::BLOCKED_RANGES as $network => $prefixBits) {
            $packed = inet_pton($network);
            if (false === $packed) {
                throw new InvalidArgumentException(sprintf('Blocked range is not an address: %s', $network));
            }

            $packedRanges[$packed] = $prefixBits;
        }

        $this->blockedRanges = $packedRanges;
    }

    public function permits(string $address): bool
    {
        if ($this->allowPrivateHosts) {
            return true;
        }

        $packed = @inet_pton($this->unmapIpv4MappedIpv6($address));
        if (false === $packed) {
            return false;
        }

        foreach ($this->blockedRanges as $network => $prefixBits) {
            if (self::sharesPrefix($packed, $network, $prefixBits)) {
                return false;
            }
        }

        return true;
    }

    private static function sharesPrefix(string $packed, string $network, int $prefixBits): bool
    {
        if (strlen($network) !== strlen($packed)) {
            return false;
        }

        $wholeBytes = intdiv($prefixBits, 8);
        if (substr($packed, 0, $wholeBytes) !== substr($network, 0, $wholeBytes)) {
            return false;
        }

        $remainingBits = $prefixBits % 8;
        if (0 === $remainingBits) {
            return true;
        }

        $mask = (0xFF << (8 - $remainingBits)) & 0xFF;

        return (ord($packed[$wholeBytes]) & $mask) === (ord($network[$wholeBytes]) & $mask);
    }

    private function unmapIpv4MappedIpv6(string $address): string
    {
        $packed = @inet_pton($address);
        if (false === $packed || 16 !== strlen($packed) || !str_starts_with($packed, self::IPV4_MAPPED_IPV6_PREFIX)) {
            return $address;
        }

        $ipv4 = inet_ntop(substr($packed, 12));

        return false === $ipv4 ? $address : $ipv4;
    }
}
