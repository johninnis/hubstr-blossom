<?php

declare(strict_types=1);

namespace Innis\Hubstr\Blossom\Infrastructure\Config;

use Innis\Hubstr\Blossom\Domain\ValueObject\HostConfig;
use Innis\Hubstr\Core\Infrastructure\Config\ConfigLoader;

final readonly class HostConfigLoader
{
    private const string ENVIRONMENT_VARIABLE = 'HUBSTR_BLOSSOM_CONFIG';

    public function load(string $defaultPath): HostConfig
    {
        return HostConfig::fromValues(new ConfigLoader(self::ENVIRONMENT_VARIABLE)->load($defaultPath));
    }
}
