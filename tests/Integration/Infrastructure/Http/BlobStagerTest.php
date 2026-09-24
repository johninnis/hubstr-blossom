<?php

declare(strict_types=1);

namespace Innis\Hubstr\Blossom\Tests\Integration\Infrastructure\Http;

use Amp\ByteStream\ReadableBuffer;
use Amp\ByteStream\ReadableIterableStream;
use Amp\Http\Server\Driver\Client;
use Amp\Http\Server\Request;
use FilesystemIterator;
use Innis\Hubstr\Blossom\Infrastructure\Filesystem\TempFileFactory;
use Innis\Hubstr\Blossom\Infrastructure\Http\BlobStager;
use Innis\Hubstr\Blossom\Tests\Support\TemporaryDirectory;
use Innis\Nostr\Blossom\Application\DTO\PendingBlob;
use Innis\Nostr\Blossom\Domain\Failure\BlobTooLargeFailure;
use InvalidArgumentException;
use League\Uri\Http;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class BlobStagerTest extends TestCase
{
    private const int MAX_BYTES = 16;

    private TemporaryDirectory $directory;

    protected function setUp(): void
    {
        $this->directory = TemporaryDirectory::create();
    }

    protected function tearDown(): void
    {
        $this->directory->remove();
    }

    public function testStagesABodyWithinTheCapAsAPendingBlobOfTheDeclaredType(): void
    {
        $staged = $this->stage(new ReadableBuffer('within the cap'), ['content-type' => 'image/png; charset=binary']);

        self::assertInstanceOf(PendingBlob::class, $staged);
        self::assertSame('within the cap', (string) file_get_contents($staged->getPath()));
        self::assertSame('image/png', (string) $staged->getMimeType());
    }

    public function testAnUndeclaredTypeDegradesToTheGenericType(): void
    {
        $staged = $this->stage(new ReadableBuffer('untyped'));

        self::assertInstanceOf(PendingBlob::class, $staged);
        self::assertTrue($staged->getMimeType()->isGeneric());
    }

    public function testStagesABodyWhoseDeclaredLengthIsExactlyTheCap(): void
    {
        $content = str_repeat('x', self::MAX_BYTES);

        $staged = $this->stage(new ReadableBuffer($content), ['content-length' => (string) self::MAX_BYTES]);

        self::assertInstanceOf(PendingBlob::class, $staged);
        self::assertSame($content, (string) file_get_contents($staged->getPath()));
    }

    public function testRefusesADeclaredLengthOverTheCapWithoutCreatingAFile(): void
    {
        $staged = $this->stage(new ReadableBuffer('never read'), ['content-length' => (string) (self::MAX_BYTES + 1)]);

        self::assertInstanceOf(BlobTooLargeFailure::class, $staged);
        self::assertSame(0, $this->stagedFileCount());
    }

    public function testCutsAnUndeclaredBodyThatCrossesTheCapAndLeavesNoFile(): void
    {
        $staged = $this->stage(new ReadableBuffer(str_repeat('x', self::MAX_BYTES + 1)));

        self::assertInstanceOf(BlobTooLargeFailure::class, $staged);
        self::assertSame(0, $this->stagedFileCount());
    }

    public function testPropagatesAReadFaultAndLeavesNoFile(): void
    {
        $failing = new ReadableIterableStream((static function (): iterable {
            yield 'partial';

            throw new RuntimeException('connection dropped');
        })());

        $fault = null;
        try {
            $this->stage($failing);
        } catch (RuntimeException $caught) {
            $fault = $caught;
        }

        self::assertSame('connection dropped', $fault?->getMessage());
        self::assertSame(0, $this->stagedFileCount());
    }

    public function testRejectsANonPositiveCap(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new BlobStager(new TempFileFactory($this->directory->getPath()), 0);
    }

    /**
     * @param array<non-empty-string, string> $headers
     */
    private function stage(ReadableIterableStream|ReadableBuffer $body, array $headers = []): PendingBlob|BlobTooLargeFailure
    {
        $request = new Request($this->createStub(Client::class), 'PUT', Http::new('http://localhost/upload'), $headers, $body);

        return new BlobStager(new TempFileFactory($this->directory->getPath()), self::MAX_BYTES)->stage($request, $request->getBody());
    }

    private function stagedFileCount(): int
    {
        return iterator_count(new FilesystemIterator($this->directory->getPath(), FilesystemIterator::SKIP_DOTS));
    }
}
