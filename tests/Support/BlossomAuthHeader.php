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

    private const string CONTENT = 'Blossom auth';

    private const string PADDING = 'a';

    public function __construct(private SignatureServiceInterface $signer)
    {
    }

    public function for(KeyPair $keyPair, string $verb, ?string $hash = null): string
    {
        return NostrAuthHeaderCodec::encodeBlossom($this->event($keyPair, $verb, $hash, self::CONTENT))
            ?? throw new LogicException('A test Blossom authorisation header cannot exceed the header length limit');
    }

    public function legacyFor(KeyPair $keyPair, string $verb, ?string $hash = null): string
    {
        return NostrAuthHeaderCodec::encode($this->event($keyPair, $verb, $hash, self::CONTENT))
            ?? throw new LogicException('A test Blossom authorisation header cannot exceed the header length limit');
    }

    public function ofLength(KeyPair $keyPair, string $verb, string $hash, int $length): string
    {
        $paddingLength = self::jsonLengthEncodingTo($length) - strlen($this->event($keyPair, $verb, $hash, '')->toJson());
        $header = NostrAuthHeaderCodec::encodeBlossom($this->event($keyPair, $verb, $hash, str_repeat(self::PADDING, max(0, $paddingLength))));

        if (null === $header || strlen($header) !== $length) {
            throw new LogicException(sprintf('A test Blossom authorisation header cannot be built at exactly %d characters', $length));
        }

        return $header;
    }

    private static function jsonLengthEncodingTo(int $headerLength): int
    {
        return intdiv(3 * ($headerLength - strlen(NostrAuthHeaderCodec::HEADER_PREFIX)), 4);
    }

    private function event(KeyPair $keyPair, string $verb, ?string $hash, string $content): Event
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
            EventContent::fromString($content),
            new TagCollection($tags),
        )->sign($keyPair, $this->signer);
    }
}
