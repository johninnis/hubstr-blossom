<?php

declare(strict_types=1);

namespace Innis\Hubstr\Blossom\Tests\Unit\Infrastructure\Network;

use Innis\Hubstr\Blossom\Infrastructure\Network\PrivateAddressGuard;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PrivateAddressGuardTest extends TestCase
{
    public function testPermitsPrivateAddressWhenAllowed(): void
    {
        self::assertTrue(new PrivateAddressGuard(true)->permits('127.0.0.1'));
    }

    #[DataProvider('blockedAddresses')]
    public function testForbidsPrivateAndReservedAddresses(string $address): void
    {
        self::assertFalse(new PrivateAddressGuard()->permits($address));
    }

    #[DataProvider('allowedAddresses')]
    public function testPermitsPublicAddresses(string $address): void
    {
        self::assertTrue(new PrivateAddressGuard()->permits($address));
    }

    #[DataProvider('ipv4MappedIpv6Addresses')]
    public function testForbidsIpv4MappedIpv6ThatEmbedsAPrivateAddress(string $address): void
    {
        self::assertFalse(new PrivateAddressGuard()->permits($address));
    }

    #[DataProvider('embeddedIpv4Addresses')]
    public function testForbidsIpv6TransitionRangesThatEmbedAnIpv4Address(string $address): void
    {
        self::assertFalse(new PrivateAddressGuard()->permits($address));
    }

    #[DataProvider('nonAddressEncodings')]
    public function testForbidsNonCanonicalOrEncodedHosts(string $host): void
    {
        self::assertFalse(new PrivateAddressGuard()->permits($host));
    }

    public function testPermitsAPublicIpv4MappedIpv6(): void
    {
        self::assertTrue(new PrivateAddressGuard()->permits('::ffff:8.8.8.8'));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function ipv4MappedIpv6Addresses(): array
    {
        return [
            'mapped loopback' => ['::ffff:127.0.0.1'],
            'mapped private 10/8' => ['::ffff:10.0.0.1'],
            'mapped link-local metadata' => ['::ffff:169.254.169.254'],
            'mapped metadata hex form' => ['::ffff:a9fe:a9fe'],
            'mapped loopback expanded' => ['0:0:0:0:0:ffff:7f00:0001'],
        ];
    }

    /**
     * @return array<string, array{string}>
     */
    public static function embeddedIpv4Addresses(): array
    {
        return [
            'nat64 loopback' => ['64:ff9b::7f00:1'],
            'nat64 public' => ['64:ff9b::808:808'],
            'local nat64' => ['64:ff9b:1::1'],
            '6to4 loopback' => ['2002:7f00:1::'],
            '6to4 public' => ['2002:808:808::'],
            'teredo' => ['2001:0:4136:e378:8000:63bf:3fff:fdd2'],
        ];
    }

    /**
     * @return array<string, array{string}>
     */
    public static function nonAddressEncodings(): array
    {
        return [
            'decimal loopback' => ['2130706433'],
            'hex loopback' => ['0x7f000001'],
            'octal loopback' => ['017700000001'],
            'dotted-decimal-ish hostname' => ['example.com'],
            'empty' => [''],
        ];
    }

    /**
     * @return array<string, array{string}>
     */
    public static function blockedAddresses(): array
    {
        return [
            'this network' => ['0.0.0.0'],
            'loopback' => ['127.0.0.1'],
            'private 10/8' => ['10.0.0.1'],
            'private 192.168' => ['192.168.1.1'],
            'private 172.16' => ['172.16.0.1'],
            'private 172.31 upper bound' => ['172.31.255.255'],
            'carrier-grade nat' => ['100.64.0.1'],
            'carrier-grade nat cloud metadata' => ['100.100.100.200'],
            'link-local metadata' => ['169.254.169.254'],
            'ietf protocol assignments' => ['192.0.0.192'],
            'documentation test-net-1' => ['192.0.2.1'],
            '6to4 relay anycast' => ['192.88.99.1'],
            'benchmarking' => ['198.18.0.1'],
            'documentation test-net-2' => ['198.51.100.1'],
            'documentation test-net-3' => ['203.0.113.1'],
            'multicast' => ['224.0.0.1'],
            'reserved 240/4' => ['240.0.0.1'],
            'broadcast' => ['255.255.255.255'],
            'ipv6 unspecified' => ['::'],
            'ipv6 loopback' => ['::1'],
            'ipv6 discard' => ['100::1'],
            'ipv6 documentation' => ['2001:db8::1'],
            'ipv6 unique-local' => ['fd00::1'],
            'ipv6 unique-local fc' => ['fc00::1'],
            'ipv6 link-local' => ['fe80::1'],
            'ipv6 site-local' => ['fec0::1'],
            'ipv6 multicast' => ['ff02::1'],
        ];
    }

    /**
     * @return array<string, array{string}>
     */
    public static function allowedAddresses(): array
    {
        return [
            'public ipv4' => ['8.8.8.8'],
            'public ipv4 cloudflare' => ['1.1.1.1'],
            'public ipv4 just above private 172' => ['172.32.0.1'],
            'public ipv4 just above cgnat' => ['100.128.0.1'],
            'public ipv6' => ['2606:4700:4700::1111'],
            'public ipv6 beside the teredo prefix' => ['2001:4860:4860::8888'],
        ];
    }
}
