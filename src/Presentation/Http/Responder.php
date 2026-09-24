<?php

declare(strict_types=1);

namespace Innis\Hubstr\Blossom\Presentation\Http;

use Amp\Http\HttpStatus;
use Amp\Http\Server\Response;
use Innis\Nostr\Blossom\Domain\Collection\BlobDescriptorCollection;
use Innis\Nostr\Blossom\Domain\Failure\BlossomFailure;
use Innis\Nostr\Blossom\Domain\ValueObject\BlobDescriptor;
use Innis\Nostr\Core\Domain\Service\JsonWireFormat;

final class Responder
{
    private function __construct()
    {
    }

    public static function respond(BlobDescriptor|BlobDescriptorCollection|BlossomFailure|null $result): Response
    {
        return match (true) {
            $result instanceof BlossomFailure => self::error($result),
            null === $result => new Response(HttpStatus::OK),
            default => self::json($result),
        };
    }

    public static function error(BlossomFailure $error): Response
    {
        return self::failure($error->category()->httpStatus(), $error->getMessage());
    }

    public static function failure(int $statusCode, string $message): Response
    {
        $response = self::json(['message' => $message], $statusCode);
        $response->setHeader('x-reason', $message);

        return $response;
    }

    private static function json(mixed $value, int $statusCode = HttpStatus::OK): Response
    {
        return new Response(
            $statusCode,
            ['content-type' => 'application/json'],
            JsonWireFormat::encode($value, JsonWireFormat::MESSAGE),
        );
    }
}
