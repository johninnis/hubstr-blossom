<?php

declare(strict_types=1);

namespace Innis\Hubstr\Blossom;

use Amp\Parallel\Worker\ContextWorkerPool;
use Amp\Parallel\Worker\WorkerPool;
use Innis\Hubstr\Blossom\Application\Port\BlobReportQueryInterface;
use Innis\Hubstr\Blossom\Domain\ValueObject\HostConfig;
use Innis\Hubstr\Blossom\Infrastructure\Filesystem\FileByteStreamer;
use Innis\Hubstr\Blossom\Infrastructure\Filesystem\FilesystemBlobInspector;
use Innis\Hubstr\Blossom\Infrastructure\Filesystem\FilesystemBlobStore;
use Innis\Hubstr\Blossom\Infrastructure\Filesystem\TempFileFactory;
use Innis\Hubstr\Blossom\Infrastructure\Http\BlobStager;
use Innis\Hubstr\Blossom\Infrastructure\Http\HttpRemoteBlobFetcher;
use Innis\Hubstr\Blossom\Infrastructure\Media\GdMediaOptimiser;
use Innis\Hubstr\Blossom\Infrastructure\Media\ImagePixelBudget;
use Innis\Hubstr\Blossom\Infrastructure\Network\PrivateAddressGuard;
use Innis\Hubstr\Blossom\Infrastructure\Persistence\CachingBlobIndex;
use Innis\Hubstr\Blossom\Infrastructure\Persistence\SqliteBlobIndex;
use Innis\Hubstr\Blossom\Infrastructure\Persistence\SqliteBlobReportStore;
use Innis\Hubstr\Blossom\Infrastructure\Process\BlossomLifecycle;
use Innis\Hubstr\Blossom\Infrastructure\Worker\WorkerBlobInspector;
use Innis\Hubstr\Blossom\Infrastructure\Worker\WorkerMediaOptimiser;
use Innis\Hubstr\Blossom\Presentation\Http\BlobIngestController;
use Innis\Hubstr\Blossom\Presentation\Http\BlobManagementController;
use Innis\Hubstr\Blossom\Presentation\Http\BlobMirrorController;
use Innis\Hubstr\Blossom\Presentation\Http\BlobPreflightController;
use Innis\Hubstr\Blossom\Presentation\Http\BlobReportController;
use Innis\Hubstr\Blossom\Presentation\Http\BlobRequestReader;
use Innis\Hubstr\Blossom\Presentation\Http\BlobRetrievalController;
use Innis\Hubstr\Blossom\Presentation\Http\BlobStreamResponder;
use Innis\Hubstr\Blossom\Presentation\Http\CorsErrorHandler;
use Innis\Hubstr\Blossom\Presentation\Http\Middleware\CorsHeaders;
use Innis\Hubstr\Blossom\Presentation\Http\Middleware\CorsMiddleware;
use Innis\Hubstr\Blossom\Presentation\Http\PreflightRoutes;
use Innis\Hubstr\Blossom\Presentation\Http\RouteTable;
use Innis\Hubstr\Core\Application\Port\LifecycleInterface;
use Innis\Hubstr\Core\Application\Port\SiteInfoProviderInterface;
use Innis\Hubstr\Core\Application\Port\TemplateRendererInterface;
use Innis\Hubstr\Core\Application\Port\VersionProviderInterface;
use Innis\Hubstr\Core\Domain\Enum\HttpMethod;
use Innis\Hubstr\Core\Domain\ValueObject\SiteInfo;
use Innis\Hubstr\Core\Infrastructure\Http\HttpServerOptions;
use Innis\Hubstr\Core\Infrastructure\Http\Route;
use Innis\Hubstr\Core\Infrastructure\Http\RouterDefinition;
use Innis\Hubstr\Core\Infrastructure\Http\StaticSiteInfoProvider;
use Innis\Hubstr\Core\Infrastructure\Persistence\SchemaMigrator;
use Innis\Hubstr\Core\Infrastructure\Persistence\SqliteDatabase;
use Innis\Hubstr\Core\Infrastructure\Templating\LatteTemplateRenderer;
use Innis\Hubstr\Core\Infrastructure\Version\ComposerVersionProvider;
use Innis\Hubstr\Core\Presentation\Http\ErrorPageResponder;
use Innis\Hubstr\Core\Presentation\Http\LandingPageResponder;
use Innis\Hubstr\Core\Presentation\Http\TemplatedErrorHandler;
use Innis\Nostr\Blossom\Application\Port\BlobIndexInterface;
use Innis\Nostr\Blossom\Application\Port\BlobInspectorInterface;
use Innis\Nostr\Blossom\Application\Port\BlobStoreInterface;
use Innis\Nostr\Blossom\Application\Port\BlossomPolicyInterface;
use Innis\Nostr\Blossom\Application\Port\MediaOptimiserInterface;
use Innis\Nostr\Blossom\Application\Port\RemoteBlobFetcherInterface;
use Innis\Nostr\Blossom\Application\Service\BlobArchive;
use Innis\Nostr\Blossom\Application\Service\BlobDescriptorFactory;
use Innis\Nostr\Blossom\Application\Service\BlobIngestor;
use Innis\Nostr\Blossom\Application\Service\BlobValidator;
use Innis\Nostr\Blossom\Application\Service\BlossomAccessGate;
use Innis\Nostr\Blossom\Application\Service\BlossomAuthValidator;
use Innis\Nostr\Blossom\Application\Service\BlossomAuthValidatorInterface;
use Innis\Nostr\Blossom\Application\Service\TenantBlossomPolicy;
use Innis\Nostr\Blossom\Application\UseCase\CheckMediaUseCase;
use Innis\Nostr\Blossom\Application\UseCase\CheckUploadUseCase;
use Innis\Nostr\Blossom\Application\UseCase\DeleteBlobUseCase;
use Innis\Nostr\Blossom\Application\UseCase\GetBlobUseCase;
use Innis\Nostr\Blossom\Application\UseCase\ListBlobsUseCase;
use Innis\Nostr\Blossom\Application\UseCase\MirrorBlobUseCase;
use Innis\Nostr\Blossom\Application\UseCase\OptimiseMediaUseCase;
use Innis\Nostr\Blossom\Application\UseCase\ReportBlobUseCase;
use Innis\Nostr\Blossom\Application\UseCase\UploadBlobUseCase;
use Innis\Nostr\Blossom\Domain\ValueObject\UploadConstraints;
use Innis\Nostr\Core\Application\Port\ClockInterface;
use Innis\Nostr\Core\Domain\Service\SignatureServiceInterface;
use Innis\Nostr\Core\Infrastructure\Crypto\Secp256k1Signer;
use Innis\Nostr\Core\Infrastructure\Time\SystemClock;
use PDO;

final class HostContainer
{
    private const string SITE_NAME = 'Hubstr Blossom';
    private const int CONCURRENCY_LIMIT = 1000;
    private const int BODY_HEADROOM_BYTES = 8192;

    private ?PDO $connection = null;
    private ?ClockInterface $clock = null;
    private ?BlobIndexInterface $index = null;
    private ?WorkerPool $workerPool = null;
    private ?SignatureServiceInterface $signatureService = null;
    private ?BlossomAccessGate $gate = null;
    private ?BlobIngestor $ingestor = null;
    private ?BlobArchive $archive = null;
    private ?BlobInspectorInterface $inspector = null;
    private ?MediaOptimiserInterface $mediaOptimiser = null;
    private ?RemoteBlobFetcherInterface $remoteBlobFetcher = null;
    private ?SqliteBlobReportStore $reports = null;
    private ?TempFileFactory $tempFiles = null;
    private ?BlobStager $stager = null;
    private ?BlobRequestReader $requestReader = null;
    private ?TemplateRendererInterface $templateRenderer = null;
    private ?VersionProviderInterface $versionProvider = null;
    private ?SiteInfoProviderInterface $siteInfoProvider = null;

    public function __construct(
        private readonly HostConfig $config,
    ) {
    }

    public function blobIndex(): BlobIndexInterface
    {
        return $this->index ??= new CachingBlobIndex(new SqliteBlobIndex($this->connection()));
    }

    public function blobReportQuery(): BlobReportQueryInterface
    {
        return $this->blobReports();
    }

    private function blobStore(): BlobStoreInterface
    {
        return new FilesystemBlobStore($this->config->getStoragePath());
    }

    private function applySchema(PDO $pdo): PDO
    {
        new SchemaMigrator($pdo)->migrate(dirname(__DIR__).'/resources/migrations');

        return $pdo;
    }

    public function routerDefinition(): RouterDefinition
    {
        $table = new RouteTable();

        $routes = [
            ...$table->retrievalRoutes($this->blobRetrievalController()),
            ...$table->managementRoutes($this->blobManagementController()),
            ...$table->ingestRoutes($this->blobIngestController()),
            ...$table->mirrorRoutes($this->blobMirrorController()),
            ...$table->preflightRoutes($this->uploadPreflightController(), $this->mediaPreflightController()),
            ...$table->reportRoutes($this->blobReportController()),
            new Route(HttpMethod::Get, '/', $this->landingPageResponder()->respond(...)),
        ];

        return new RouterDefinition(
            [...$routes, ...PreflightRoutes::forRoutes(...$routes)],
            // Deliberate: the error handler carries CORS as well as the middleware, for the errors the driver answers before any middleware runs — see ADR-0009
            new CorsErrorHandler(new TemplatedErrorHandler($this->errorPageResponder()), new CorsHeaders()),
            dirname(__DIR__).'/public',
        );
    }

    public function lifecycle(): LifecycleInterface
    {
        return new BlossomLifecycle($this->workerPool());
    }

    public function httpServerOptions(): HttpServerOptions
    {
        $maxUploadBytes = $this->uploadConstraints()->getMaxUploadBytes();

        return HttpServerOptions::create(
            self::CONCURRENCY_LIMIT,
            $maxUploadBytes + self::BODY_HEADROOM_BYTES,
            [new CorsMiddleware(new CorsHeaders())],
        );
    }

    private function landingPageResponder(): LandingPageResponder
    {
        return new LandingPageResponder('index.latte', $this->templateRenderer(), $this->siteInfoProvider());
    }

    private function errorPageResponder(): ErrorPageResponder
    {
        return new ErrorPageResponder('error.latte', $this->templateRenderer(), $this->siteInfoProvider());
    }

    private function siteInfoProvider(): SiteInfoProviderInterface
    {
        return $this->siteInfoProvider ??= new StaticSiteInfoProvider(
            new SiteInfo(self::SITE_NAME, $this->versionProvider()->getVersion(), $this->ownerNpub()),
        );
    }

    private function ownerNpub(): string
    {
        return $this->config->getServerConfig()->getTenantPubkeys()->toArray()[0]->toBech32();
    }

    private function versionProvider(): VersionProviderInterface
    {
        return $this->versionProvider ??= new ComposerVersionProvider();
    }

    private function templateRenderer(): TemplateRendererInterface
    {
        return $this->templateRenderer ??= LatteTemplateRenderer::create(
            dirname(__DIR__).'/templates',
            dirname(__DIR__).'/var/cache/latte',
        );
    }

    private function blobRetrievalController(): BlobRetrievalController
    {
        return new BlobRetrievalController(
            new GetBlobUseCase($this->blobArchive(), $this->gate()),
            new BlobStreamResponder(new FileByteStreamer()),
            $this->blobRequestReader(),
        );
    }

    private function blobManagementController(): BlobManagementController
    {
        return new BlobManagementController(
            new DeleteBlobUseCase($this->blobArchive(), $this->gate()),
            new ListBlobsUseCase($this->blobIndex(), $this->gate()),
            $this->blobRequestReader(),
        );
    }

    private function blobIngestController(): BlobIngestController
    {
        return new BlobIngestController(
            new UploadBlobUseCase($this->gate(), $this->ingestor()),
            new OptimiseMediaUseCase($this->mediaOptimiser(), $this->gate(), $this->ingestor()),
            $this->blobRequestReader(),
        );
    }

    private function blobMirrorController(): BlobMirrorController
    {
        return new BlobMirrorController(
            new MirrorBlobUseCase($this->remoteBlobFetcher(), $this->gate(), $this->ingestor()),
            $this->blobRequestReader(),
        );
    }

    private function uploadPreflightController(): BlobPreflightController
    {
        $constraints = $this->uploadConstraints();

        return new BlobPreflightController(
            new CheckUploadUseCase($this->gate(), $constraints)->execute(...),
            $constraints,
            $this->blobRequestReader(),
        );
    }

    private function mediaPreflightController(): BlobPreflightController
    {
        $constraints = $this->uploadConstraints();

        return new BlobPreflightController(
            new CheckMediaUseCase($this->mediaOptimiser(), $this->gate(), $constraints)->execute(...),
            $constraints,
            $this->blobRequestReader(),
        );
    }

    private function blobReportController(): BlobReportController
    {
        return new BlobReportController(
            new ReportBlobUseCase($this->blobReports(), $this->signatureService()),
            $this->blobRequestReader(),
        );
    }

    private function uploadConstraints(): UploadConstraints
    {
        return $this->config->getServerConfig()->getUploadConstraints();
    }

    private function blobRequestReader(): BlobRequestReader
    {
        return $this->requestReader ??= new BlobRequestReader($this->stager());
    }

    private function blobArchive(): BlobArchive
    {
        return $this->archive ??= new BlobArchive($this->blobStore(), $this->blobIndex());
    }

    private function ingestor(): BlobIngestor
    {
        return $this->ingestor ??= new BlobIngestor(
            new BlobValidator($this->blobInspector(), $this->uploadConstraints(), $this->gate()),
            new BlobDescriptorFactory($this->config->getServerConfig()->getIdentity(), $this->clock()),
            $this->blobArchive(),
        );
    }

    private function gate(): BlossomAccessGate
    {
        return $this->gate ??= new BlossomAccessGate($this->authValidator(), $this->policy());
    }

    private function authValidator(): BlossomAuthValidatorInterface
    {
        return new BlossomAuthValidator(
            $this->signatureService(),
            $this->clock(),
            $this->config->getServerConfig()->getIdentity(),
        );
    }

    private function policy(): BlossomPolicyInterface
    {
        // Deliberate: reads are public and there is no key to change that — a blob's URL travels in a public event, and content that must stay private is encrypted before upload — see ADR-0007
        return new TenantBlossomPolicy($this->config->getServerConfig()->getTenantPubkeys());
    }

    private function blobInspector(): BlobInspectorInterface
    {
        return $this->inspector ??= new WorkerBlobInspector($this->workerPool(), new FilesystemBlobInspector($this->pixelBudget()));
    }

    private function pixelBudget(): ImagePixelBudget
    {
        return new ImagePixelBudget($this->config->getMaxImagePixels());
    }

    private function mediaOptimiser(): MediaOptimiserInterface
    {
        return $this->mediaOptimiser ??= new WorkerMediaOptimiser($this->workerPool(), new GdMediaOptimiser($this->tempFiles(), $this->pixelBudget()));
    }

    private function remoteBlobFetcher(): RemoteBlobFetcherInterface
    {
        return $this->remoteBlobFetcher ??= new HttpRemoteBlobFetcher(
            $this->uploadConstraints()->getMaxUploadBytes(),
            new PrivateAddressGuard($this->config->allowsPrivateMirrorHosts()),
            $this->stager(),
        );
    }

    private function tempFiles(): TempFileFactory
    {
        return $this->tempFiles ??= new TempFileFactory($this->config->getStoragePath().'/tmp');
    }

    private function stager(): BlobStager
    {
        return $this->stager ??= new BlobStager($this->tempFiles(), $this->uploadConstraints()->getMaxUploadBytes());
    }

    private function blobReports(): SqliteBlobReportStore
    {
        return $this->reports ??= new SqliteBlobReportStore($this->connection(), $this->clock());
    }

    private function signatureService(): SignatureServiceInterface
    {
        return $this->signatureService ??= Secp256k1Signer::create();
    }

    private function clock(): ClockInterface
    {
        return $this->clock ??= new SystemClock();
    }

    private function connection(): PDO
    {
        return $this->connection ??= $this->applySchema(SqliteDatabase::atPath($this->config->getRuntime()->getDatabasePath())->connect());
    }

    private function workerPool(): WorkerPool
    {
        return $this->workerPool ??= new ContextWorkerPool(
            limit: $this->config->getWorkerPoolLimit() > 0 ? $this->config->getWorkerPoolLimit() : WorkerPool::DEFAULT_WORKER_LIMIT,
        );
    }
}
