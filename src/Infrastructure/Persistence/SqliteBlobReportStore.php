<?php

declare(strict_types=1);

namespace Innis\Hubstr\Blossom\Infrastructure\Persistence;

use Innis\Hubstr\Blossom\Application\Port\BlobReportQueryInterface;
use Innis\Hubstr\Blossom\Domain\Collection\BlobReportCollection;
use Innis\Hubstr\Blossom\Domain\Exception\CorruptRowException;
use Innis\Hubstr\Blossom\Domain\ValueObject\BlobReport;
use Innis\Nostr\Blossom\Application\Port\BlobReportStoreInterface;
use Innis\Nostr\Blossom\Domain\ValueObject\BlobHash;
use Innis\Nostr\Blossom\Domain\ValueObject\ListQuery;
use Innis\Nostr\Core\Application\Port\ClockInterface;
use Innis\Nostr\Core\Domain\Entity\Event;
use Innis\Nostr\Core\Domain\ValueObject\Timestamp;
use Override;
use PDO;

final readonly class SqliteBlobReportStore implements BlobReportStoreInterface, BlobReportQueryInterface
{
    private const string TABLE = 'reports';

    public function __construct(
        private PDO $pdo,
        private ClockInterface $clock,
    ) {
    }

    #[Override]
    public function save(BlobHash $hash, Event $report): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO reports (blob_sha256, event_json, created_at) VALUES (:blob_sha256, :event_json, :created_at)'
        );

        $stmt->execute([
            ':blob_sha256' => $hash->toHex(),
            ':event_json' => $report->toJson(),
            ':created_at' => $this->clock->now()->toInt(),
        ]);
    }

    #[Override]
    public function list(ListQuery $query): BlobReportCollection
    {
        $window = new TimeWindowClause('created_at', $query);
        $conditions = $window->getConditions();

        $stmt = $this->pdo->prepare(sprintf(
            'SELECT * FROM reports%s ORDER BY created_at DESC, id DESC LIMIT :limit',
            [] === $conditions ? '' : ' WHERE '.implode(' AND ', $conditions),
        ));
        foreach ($window->getParameters() as $parameter => $value) {
            $stmt->bindValue($parameter, $value, PDO::PARAM_INT);
        }
        $stmt->bindValue(':limit', $query->getLimit(), PDO::PARAM_INT);
        $stmt->execute();

        $reports = [];
        while (is_array($row = $stmt->fetch(PDO::FETCH_ASSOC))) {
            $reports[] = $this->hydrateReport(new StoredRow(self::TABLE, $row));
        }

        return new BlobReportCollection($reports);
    }

    private function hydrateReport(StoredRow $row): BlobReport
    {
        $hashHex = $row->string('blob_sha256');
        $hash = BlobHash::tryFromHex($hashHex) ?? throw CorruptRowException::invalidHash(self::TABLE, $hashHex);
        $event = Event::tryFromJson($row->string('event_json')) ?? throw CorruptRowException::invalidEvent(self::TABLE, $row->int('id'));

        return new BlobReport($hash, $event, Timestamp::fromInt($row->int('created_at')));
    }
}
