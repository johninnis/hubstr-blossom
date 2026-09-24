<?php

declare(strict_types=1);

use Amp\Socket\ResourceServerSocketFactory;
use Innis\Hubstr\Blossom\HostContainer;
use Innis\Hubstr\Blossom\Infrastructure\Config\HostConfig;
use Innis\Hubstr\Core\Application\Service\Kernel;
use Innis\Hubstr\Core\Infrastructure\Http\HttpServerFactory;
use Innis\Hubstr\Core\Infrastructure\Logging\LoggerFactory;
use Innis\Hubstr\Core\Infrastructure\Process\AmphpShutdownSignal;

use function Amp\ByteStream\getStdout;

require_once dirname(__DIR__).'/vendor/autoload.php';

$config = HostConfig::load(dirname(__DIR__).'/config/blossom.php');
$logger = new LoggerFactory(getStdout(), $config->getRuntime()->getLogLevel())->create('blossom');

$container = new HostContainer($config);
$factory = new HttpServerFactory($logger, new ResourceServerSocketFactory());
$socketServer = $factory->createSocketServer($config->getRuntime()->getBinding(), $container->httpServerOptions());
$server = $factory->createServer($socketServer, $container->routerDefinition());

new Kernel($logger, new AmphpShutdownSignal(), $container->lifecycle())->run($server, 'Hubstr Blossom started', [
    'storage' => $config->getStoragePath(),
    'database' => $config->getRuntime()->getDatabasePath(),
]);
