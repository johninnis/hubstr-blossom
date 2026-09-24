<?php

declare(strict_types=1);

namespace Innis\Hubstr\Blossom\Domain\ValueObject;

use Innis\Nostr\Blossom\Domain\ValueObject\BlobHash;
use Innis\Nostr\Core\Domain\Entity\Event;
use Innis\Nostr\Core\Domain\ValueObject\Identity\PublicKey;
use Innis\Nostr\Core\Domain\ValueObject\Tag\Tag;
use Innis\Nostr\Core\Domain\ValueObject\Tag\TagType;
use Innis\Nostr\Core\Domain\ValueObject\Timestamp;

final readonly class BlobReport
{
    private const int REPORT_TYPE_INDEX = 1;

    public function __construct(
        private BlobHash $blob,
        private Event $report,
        private Timestamp $receivedAt,
    ) {
    }

    public function getBlob(): BlobHash
    {
        return $this->blob;
    }

    public function getReport(): Event
    {
        return $this->report;
    }

    public function getReceivedAt(): Timestamp
    {
        return $this->receivedAt;
    }

    public function getReporter(): PublicKey
    {
        return $this->report->getPubkey();
    }

    public function getType(): ?string
    {
        $naming = array_find(
            $this->report->getTags()->findByType(TagType::sha256()),
            fn (Tag $tag): bool => $tag->getValue() === $this->blob->toHex(),
        );

        return $naming?->getValue(self::REPORT_TYPE_INDEX);
    }

    public function getReason(): string
    {
        return (string) $this->report->getContent();
    }
}
