<?php

declare(strict_types=1);

namespace Innis\Hubstr\Blossom\Tests\Unit\Presentation\Http;

use Amp\Http\HttpStatus;
use Amp\Http\Server\Driver\Client;
use Amp\Http\Server\Request;
use Amp\Http\Server\Response;
use Closure;
use Innis\Hubstr\Blossom\Presentation\Http\PreflightRoutes;
use Innis\Hubstr\Core\Domain\Enum\HttpMethod;
use Innis\Hubstr\Core\Infrastructure\Http\Route;
use League\Uri\Http;
use PHPUnit\Framework\TestCase;

final class PreflightRoutesTest extends TestCase
{
    private Closure $noop;

    private Request $request;

    protected function setUp(): void
    {
        $this->noop = static fn (Request $request): Response => new Response();
        $this->request = new Request(
            $this->createStub(Client::class),
            'OPTIONS',
            Http::new('http://localhost/'),
        );
    }

    public function testEmitsOneOptionsRoutePerPatternAdvertisingTheRequiredMethods(): void
    {
        $allow = $this->allowByPattern(
            new Route(HttpMethod::Get, '/{sha256:[0-9a-f]{64}}', $this->noop),
            new Route(HttpMethod::Head, '/{sha256:[0-9a-f]{64}}', $this->noop),
            new Route(HttpMethod::Delete, '/{sha256:[0-9a-f]{64}}', $this->noop),
            new Route(HttpMethod::Put, '/upload', $this->noop),
            new Route(HttpMethod::Head, '/upload', $this->noop),
            new Route(HttpMethod::Get, '/list/{pubkey:[0-9a-f]{64}}', $this->noop),
        );

        self::assertSame('DELETE, GET, HEAD, OPTIONS, PUT', $allow['/{sha256:[0-9a-f]{64}}']);
        self::assertSame('DELETE, GET, HEAD, OPTIONS, PUT', $allow['/upload']);
        self::assertSame('DELETE, GET, HEAD, OPTIONS, PUT', $allow['/list/{pubkey:[0-9a-f]{64}}']);
    }

    public function testAdvertisesTheRequiredMethodsEvenWhenTheRouteOnlyImplementsPut(): void
    {
        $allow = $this->allowByPattern(
            new Route(HttpMethod::Put, '/upload', $this->noop),
        );

        self::assertSame('DELETE, GET, HEAD, OPTIONS, PUT', $allow['/upload']);
    }

    public function testEachPreflightRouteIsAnOptionsRouteRespondingWith204(): void
    {
        $routes = PreflightRoutes::forRoutes(new Route(HttpMethod::Put, '/upload', $this->noop));

        self::assertCount(1, $routes);
        self::assertSame(HttpMethod::Options, $routes[0]->getMethod());
        self::assertSame('/upload', $routes[0]->getPattern());
        self::assertSame(HttpStatus::NO_CONTENT, ($routes[0]->getHandler())($this->request)->getStatus());
    }

    /**
     * @return array<string, string|null>
     */
    private function allowByPattern(Route ...$routes): array
    {
        $allow = [];
        foreach (PreflightRoutes::forRoutes(...$routes) as $route) {
            $allow[$route->getPattern()] = ($route->getHandler())($this->request)->getHeader('access-control-allow-methods');
        }

        return $allow;
    }
}
