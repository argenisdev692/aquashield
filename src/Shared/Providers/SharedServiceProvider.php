<?php

declare(strict_types=1);

namespace Shared\Providers;

use Dedoc\Scramble\Scramble;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\View as ViewFactory;
use Illuminate\Support\ServiceProvider;
use Shared\Domain\Ports\AuditPort;
use Shared\Domain\Ports\ExportPort;
use Shared\Domain\Ports\StoragePort;
use Shared\Infrastructure\AI\AIClientInterface;
use Shared\Infrastructure\AI\LaravelAIAdapter;
use Shared\Infrastructure\AI\PromptCache\PromptCacheScope;
use Shared\Infrastructure\Audit\SpatieActivityLogAdapter;
use Shared\Infrastructure\Company\CompanyProfile;
use Shared\Infrastructure\Console\Commands\PingRedisCommand;
use Shared\Infrastructure\Console\Commands\SyncR2CorsCommand;
use Shared\Infrastructure\Export\SimpleExcelExportAdapter;
use Shared\Infrastructure\Mail\MailInterface;
use Shared\Infrastructure\Mail\ResendMailAdapter;
use Shared\Infrastructure\OpenApi\SpatieDataToSchema;
use Shared\Infrastructure\Research\FirecrawlClientInterface;
use Shared\Infrastructure\Research\FirecrawlScrapeAdapter;
use Shared\Infrastructure\Research\TavilyClientInterface;
use Shared\Infrastructure\Research\TavilyResearchAdapter;
use Shared\Infrastructure\Resilience\CircuitBreaker\CircuitBreaker;
use Shared\Infrastructure\Resilience\CircuitBreaker\CircuitBreakerInterface;
use Shared\Infrastructure\Storage\R2StorageAdapter;

/**
 * Wires the cross-cutting Shared ports to their concrete adapters.
 * Registered in bootstrap/providers.php.
 */
final class SharedServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(AuditPort::class, SpatieActivityLogAdapter::class);
        $this->app->bind(StoragePort::class, R2StorageAdapter::class);
        // CSV/Excel handled here; PDF delegated to DomPdfExportAdapter (auto-resolved).
        $this->app->bind(ExportPort::class, SimpleExcelExportAdapter::class);
        $this->app->bind(AIClientInterface::class, LaravelAIAdapter::class);
        // One scope per request/job: carries the active CacheablePrompt from
        // PromptCachingAIClient to the agent's providerOptions(). `scoped` (not
        // `singleton`) so a long-lived worker or Octane request cannot observe
        // the previous lifecycle's prompt.
        $this->app->scoped(PromptCacheScope::class);
        $this->app->bind(TavilyClientInterface::class, TavilyResearchAdapter::class);
        $this->app->bind(FirecrawlClientInterface::class, FirecrawlScrapeAdapter::class);

        // The outbound provider is Resend. The adapter is intentionally the
        // only implementation: resilience (HTTP timeout + circuit breaker)
        // lives in the shared mail transport, not in a second provider class.
        $this->app->bind(MailInterface::class, ResendMailAdapter::class);

        // NOTE: no `csp-nonce` binding here on purpose. The single source is
        // spatie/laravel-csp (`csp-nonce` scoped binding + `@cspNonce` Blade
        // directive + the `nonce-…` CSP header). A second binding in this
        // provider would shadow it (boot-order dependent) and split the
        // header nonce from the view nonce — browsers would block the bundle.
        $this->app->singleton(
            CircuitBreakerInterface::class,
            static fn ($app): CircuitBreaker => new CircuitBreaker($app->make(Cache::class)),
        );
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                PingRedisCommand::class,
                SyncR2CorsCommand::class,
            ]);
        }

        // Without this, every Spatie Data response is documented as an empty
        // schema — Scramble's own Laravel-Data support is a paid feature.
        Scramble::registerExtension(SpatieDataToSchema::class);

        // Inject company branding (base64 logos + contact block) into the shared
        // corporate PDF layout so every export controller stays branding-agnostic.
        ViewFactory::composer('exports.pdf.layout', static function (View $view): void {
            $view->with('company', CompanyProfile::pdfBranding());
        });
    }
}
