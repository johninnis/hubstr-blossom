<?php

declare(strict_types=1);

namespace Innis\Hubstr\Blossom\Tests\Support;

use Innis\Nostr\Core\Domain\Collection\TagCollection;
use Innis\Nostr\Core\Domain\Factory\RumourFactory;
use Innis\Nostr\Core\Domain\Service\SignatureServiceInterface;
use Innis\Nostr\Core\Domain\ValueObject\Content\EventContent;
use Innis\Nostr\Core\Domain\ValueObject\Content\EventKind;
use Innis\Nostr\Core\Domain\ValueObject\Identity\KeyPair;
use Innis\Nostr\Core\Domain\ValueObject\Tag\Tag;
use Innis\Nostr\Core\Domain\ValueObject\Tag\TagType;

final readonly class BlossomAuthHeader
{
    private const int LIFETIME_SECONDS = 3600;

    public function __construct(private SignatureServiceInterface $signer)
    {
    }

    public function for(KeyPair $keyPair, string $verb, ?string $hash = null): string
    {
        $tags = [
            new Tag(TagType::hashtag(), [$verb]),
            new Tag(TagType::expiration(), [(string) (time() + self::LIFETIME_SECONDS)]),
        ];

        if (null !== $hash) {
            $tags[] = new Tag(TagType::sha256(), [$hash]);
        }

        $event = RumourFactory::createCustomKind(
            $keyPair->getPublicKey(),
            EventKind::fromInt(EventKind::BLOSSOM_BLOB),
            EventContent::fromString('Blossom auth'),
            new TagCollection($tags),
        )->sign($keyPair, $this->signer);

        return 'Nostr '.base64_encode((string) json_encode($event->toArray()));
    }
}
