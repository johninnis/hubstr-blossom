<?php

declare(strict_types=1);

namespace Innis\Hubstr\Blossom\Tests\Integration\Presentation\Http;

use Amp\Http\HttpStatus;
use Innis\Hubstr\Blossom\Tests\Support\TemporaryDirectory;
use Innis\Hubstr\Core\Domain\ValueObject\SiteInfo;
use Innis\Hubstr\Core\Infrastructure\Http\StaticSiteInfoProvider;
use Innis\Hubstr\Core\Infrastructure\Templating\LatteTemplateRenderer;
use Innis\Hubstr\Core\Presentation\Http\ErrorPageResponder;
use Innis\Hubstr\Core\Presentation\Http\LandingPageResponder;
use PHPUnit\Framework\TestCase;

use function Amp\ByteStream\buffer;

final class ShippedTemplatesTest extends TestCase
{
    private TemporaryDirectory $cacheDirectory;
    private LatteTemplateRenderer $renderer;
    private StaticSiteInfoProvider $site;

    protected function setUp(): void
    {
        $this->cacheDirectory = TemporaryDirectory::create();
        $this->renderer = LatteTemplateRenderer::create(dirname(__DIR__, 4).'/templates', $this->cacheDirectory->getPath());
        $this->site = new StaticSiteInfoProvider(new SiteInfo('Test Blossom', '1.2.3', 'npub1owner'));
    }

    protected function tearDown(): void
    {
        $this->cacheDirectory->remove();
    }

    public function testTheErrorPageRendersWithWhatTheResponderPasses(): void
    {
        $response = new ErrorPageResponder('error.latte', $this->renderer, $this->site)->respond(HttpStatus::NOT_FOUND, 'Not Found');

        self::assertStringContainsString('<p>404 Not Found</p>', buffer($response->getBody()));
    }

    public function testTheLandingPageRendersWithWhatTheResponderPasses(): void
    {
        $response = new LandingPageResponder('index.latte', $this->renderer, $this->site)->respond();

        self::assertStringContainsString('<p class="pubkey">npub1owner</p>', buffer($response->getBody()));
    }
}
