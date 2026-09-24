<?php

declare(strict_types=1);

namespace Innis\Hubstr\Blossom\Domain\Collection;

use Innis\Hubstr\Blossom\Domain\ValueObject\BlobReport;
use Innis\Nostr\Core\Domain\Collection\TypedCollection;
use Override;

/**
 * @extends TypedCollection<BlobReport>
 */
final class BlobReportCollection extends TypedCollection
{
    #[Override]
    protected function elementType(): string
    {
        return BlobReport::class;
    }
}
