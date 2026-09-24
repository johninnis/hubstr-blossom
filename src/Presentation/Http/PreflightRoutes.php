<?php

declare(strict_types=1);

namespace Innis\Hubstr\Blossom\Presentation\Http;

use Amp\Http\HttpStatus;
use Amp\Http\Server\Response;
use Innis\Hubstr\Core\Domain\Enum\HttpMethod;
use Innis\Hubstr\Core\Infrastructure\Http\Route;

final class PreflightRoutes
{
    private function __construct()
    {
    }

    /**
     * @return list<Route>
     */
    public static function forRoutes(Route ...$routes): array
    {
        $methodsByPattern = [];
        foreach ($routes as $route) {
            $methodsByPattern[$route->getPattern()][$route->getMethod()->value] = true;
        }

        $preflight = [];
        foreach ($methodsByPattern as $pattern => $methods) {
            if (isset($methods[HttpMethod::Get->value])) {
                $methods[HttpMethod::Head->value] = true;
            }
            $methods[HttpMethod::Options->value] = true;

            $names = array_keys($methods);
            sort($names);
            $allowMethods = implode(', ', $names);

            $preflight[] = new Route(HttpMethod::Options, (string) $pattern, static fn (): Response => new Response(
                HttpStatus::NO_CONTENT,
                ['access-control-allow-methods' => $allowMethods],
            ));
        }

        return $preflight;
    }
}
