<?php

declare(strict_types=1);

namespace Innis\Hubstr\Blossom\Tests\Support;

use Innis\Nostr\Core\Domain\Collection\TagCollection;
use Innis\Nostr\Core\Domain\Entity\Event;
use Innis\Nostr\Core\Domain\Service\NostrAuthHeaderCodec;
use Innis\Nostr\Core\Domain\Service\SignatureServiceInterface;
use Innis\Nostr\Core\Domain\ValueObject\Content\EventContent;
use Innis\Nostr\Core\Domain\ValueObject\Content\EventKind;
use Innis\Nostr\Core\Domain\ValueObject\Identity\KeyPair;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\Rumour;
use Innis\Nostr\Core\Domain\ValueObject\Tag\Tag;
use Innis\Nostr\Core\Domain\ValueObject\Tag\TagType;
use LogicException;

final readonly class BlossomAuthHeader
{
    private const int LIFETIME_SECONDS = 3600;

    public function __construct(private SignatureServiceInterface $signer)
    {
    }

    public function for(KeyPair $keyPair, string $verb, ?string $hash = null): string
    {
        return NostrAuthHeaderCodec::encodeBlossom($this->event($keyPair, $verb, $hash))
            ?? throw new LogicException('A test Blossom authorisation header cannot exceed the header length limit');
    }

    public function legacyFor(KeyPair $keyPair, string $verb, ?string $hash = null): string
    {
        return NostrAuthHeaderCodec::encode($this->event($keyPair, $verb, $hash))
            ?? throw new LogicException('A test Blossom authorisation header cannot exceed the header length limit');
    }

    private function event(KeyPair $keyPair, string $verb, ?string $hash): Event
    {
        $tags = [
            Tag::fromArray([TagType::HASHTAG, $verb]),
            Tag::fromArray([TagType::EXPIRATION, (string) (time() + self::LIFETIME_SECONDS)]),
        ];

        if (null !== $hash) {
            $tags[] = Tag::fromArray([TagType::SHA256, $hash]);
        }

        return Rumour::draft(
            $keyPair->getPublicKey(),
            EventKind::fromInt(EventKind::BLOSSOM_AUTHORISATION),
            EventContent::fromString('Blossom auth'),
            new TagCollection($tags),
        )->sign($keyPair, $this->signer);
    }
}
