<?php

declare(strict_types=1);

namespace Innis\Hubstr\Blossom\Application\Port;

use Innis\Hubstr\Blossom\Domain\Collection\BlobReportCollection;
use Innis\Nostr\Blossom\Domain\ValueObject\ListQuery;

interface BlobReportQueryInterface
{
    public function list(ListQuery $query): BlobReportCollection;
}
