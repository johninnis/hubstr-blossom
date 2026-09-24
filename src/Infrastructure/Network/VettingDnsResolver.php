<?php

declare(strict_types=1);

namespace Innis\Hubstr\Blossom\Infrastructure\Network;

use Amp\Cancellation;
use Amp\Dns\DnsException;
use Amp\Dns\DnsRecord;
use Amp\Dns\DnsResolver;
use Override;

final readonly class VettingDnsResolver implements DnsResolver
{
    public function __construct(
        private DnsResolver $resolver,
        private PrivateAddressGuard $guard,
    ) {
    }

    #[Override]
    public function resolve(string $name, ?int $typeRestriction = null, ?Cancellation $cancellation = null): array
    {
        $records = $this->resolver->resolve($name, $typeRestriction, $cancellation);
        $this->vet($name, $records);

        return $records;
    }

    #[Override]
    public function query(string $name, int $type, ?Cancellation $cancellation = null): array
    {
        $records = $this->resolver->query($name, $type, $cancellation);
        $this->vet($name, $records);

        return $records;
    }

    /**
     * @param list<DnsRecord> $records
     */
    private function vet(string $name, array $records): void
    {
        foreach ($records as $record) {
            $isAddress = DnsRecord::A === $record->getType() || DnsRecord::AAAA === $record->getType();
            if ($isAddress && !$this->guard->permits($record->getValue())) {
                throw new DnsException(sprintf('Refusing to resolve "%s" to a private or reserved address: %s', $name, $record->getValue()));
            }
        }
    }
}
