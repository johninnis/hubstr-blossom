<?php

declare(strict_types=1);

namespace Innis\Hubstr\Blossom\Presentation\Cli;

use DateTimeInterface;
use Innis\Nostr\Blossom\Application\Port\BlobIndexInterface;
use Innis\Nostr\Blossom\Domain\ValueObject\ListQuery;
use Innis\Nostr\Blossom\Domain\ValueObject\TenantPubkeys;

final readonly class ListBlobsCommand
{
    public function __construct(
        private BlobIndexInterface $index,
        private TenantPubkeys $tenants,
    ) {
    }

    public function run(ListQuery $query): string
    {
        $sections = [];
        foreach ($this->tenants->toArray() as $tenant) {
            $blobs = $this->index->list($tenant, $query);
            if ($blobs->isEmpty()) {
                continue;
            }

            $lines = [sprintf('Tenant %s, %d blob(s), newest first:', $tenant->toHex(), count($blobs))];
            foreach ($blobs as $blob) {
                $lines[] = sprintf(
                    '  %s  %s  %s  %s',
                    $blob->getSha256()->toHex(),
                    str_pad((string) $blob->getType(), 20),
                    str_pad(ByteFormatter::format($blob->getSize()), 10),
                    $blob->getUploaded()->toDateTime()->format(DateTimeInterface::ATOM),
                );
            }

            $sections[] = implode("\n", $lines);
        }

        if ([] === $sections) {
            return "No blobs stored.\n";
        }

        return implode("\n", $sections)."\n";
    }
}
