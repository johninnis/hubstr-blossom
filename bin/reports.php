<?php

declare(strict_types=1);

use Innis\Hubstr\Blossom\HostContainer;
use Innis\Hubstr\Blossom\Infrastructure\Config\HostConfigLoader;
use Innis\Hubstr\Blossom\Presentation\Cli\ListOptionFailure;
use Innis\Hubstr\Blossom\Presentation\Cli\ListOptions;
use Innis\Hubstr\Blossom\Presentation\Cli\ListReportsCommand;

require_once dirname(__DIR__).'/vendor/autoload.php';

$query = ListOptions::toQuery(getopt('', ListOptions::LONG_OPTIONS) ?: []);

if ($query instanceof ListOptionFailure) {
    fwrite(STDERR, $query->getMessage()."\n");
    exit(2);
}

$config = new HostConfigLoader()->load(dirname(__DIR__).'/config/blossom.php');
$command = new ListReportsCommand(new HostContainer($config)->blobReportQuery());

fwrite(STDOUT, $command->run($query));
