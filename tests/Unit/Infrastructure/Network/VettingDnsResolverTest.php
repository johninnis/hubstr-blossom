<?php

declare(strict_types=1);

namespace Innis\Hubstr\Blossom\Tests\Unit\Infrastructure\Network;

use Amp\Dns\DnsException;
use Amp\Dns\DnsRecord;
use Amp\Dns\DnsResolver;
use Innis\Hubstr\Blossom\Infrastructure\Network\PrivateAddressGuard;
use Innis\Hubstr\Blossom\Infrastructure\Network\VettingDnsResolver;
use PHPUnit\Framework\TestCase;

final class VettingDnsResolverTest extends TestCase
{
    public function testReturnsRecordsThatResolveToPublicAddresses(): void
    {
        $records = [new DnsRecord('8.8.8.8', DnsRecord::A)];

        self::assertSame($records, $this->vetting($records, new PrivateAddressGuard())->resolve('example.com'));
    }

    public function testRejectsResolutionToAPrivateAddress(): void
    {
        $resolver = $this->vetting([new DnsRecord('169.254.169.254', DnsRecord::A)], new PrivateAddressGuard());

        $this->expectException(DnsException::class);
        $this->expectExceptionMessage('private or reserved');

        $resolver->resolve('metadata.example.com');
    }

    public function testRejectsWhenAnyResolvedAddressIsPrivate(): void
    {
        $resolver = $this->vetting(
            [new DnsRecord('8.8.8.8', DnsRecord::A), new DnsRecord('10.0.0.1', DnsRecord::A)],
            new PrivateAddressGuard(),
        );

        $this->expectException(DnsException::class);

        $resolver->resolve('mixed.example.com');
    }

    public function testAllowsPrivateAddressesWhenGuardPermits(): void
    {
        $records = [new DnsRecord('127.0.0.1', DnsRecord::A)];

        self::assertSame($records, $this->vetting($records, new PrivateAddressGuard(true))->resolve('localhost'));
    }

    /**
     * @param list<DnsRecord> $records
     */
    private function vetting(array $records, PrivateAddressGuard $guard): VettingDnsResolver
    {
        $inner = $this->createStub(DnsResolver::class);
        $inner->method('resolve')->willReturn($records);
        $inner->method('query')->willReturn($records);

        return new VettingDnsResolver($inner, $guard);
    }
}
