<?php

declare(strict_types=1);

namespace Innis\Hubstr\Blossom\Presentation\Cli;

use DateTimeInterface;
use Innis\Hubstr\Blossom\Application\Port\BlobReportQueryInterface;
use Innis\Hubstr\Blossom\Domain\ValueObject\BlobReport;
use Innis\Nostr\Blossom\Domain\ValueObject\ListQuery;

final readonly class ListReportsCommand
{
    private const string CONTROL_CHARACTERS = '/[\x00-\x1f\x7f]+/';
    private const int TYPE_COLUMN_WIDTH = 14;

    public function __construct(private BlobReportQueryInterface $reports)
    {
    }

    public function run(ListQuery $query): string
    {
        $reports = $this->reports->list($query);
        if ($reports->isEmpty()) {
            return "No reports stored.\n";
        }

        $lines = [sprintf('%d report(s), newest first:', count($reports))];
        foreach ($reports as $report) {
            $lines[] = $this->line($report);
        }

        return implode("\n", $lines)."\n";
    }

    private function line(BlobReport $report): string
    {
        return sprintf(
            '  %s  %s  %s  %s  %s',
            $report->getReceivedAt()->toDateTime()->format(DateTimeInterface::ATOM),
            $report->getBlob()->toHex(),
            str_pad($report->getType() ?? '-', self::TYPE_COLUMN_WIDTH),
            $report->getReporter()->toBech32(),
            (string) preg_replace(self::CONTROL_CHARACTERS, ' ', $report->getReason()),
        );
    }
}
