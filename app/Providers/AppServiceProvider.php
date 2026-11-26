<?php

declare(strict_types=1);

namespace App\Providers;

use App\Listeners\AuthEventSubscriber;
use App\Listeners\GuardDestructiveCommandsOnRemoteDatabase;
use GuzzleHttp\Client as GuzzleClient;
use Illuminate\Console\Events\CommandStarting;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Mail\Transport\ResendTransport;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Resend\Client as ResendClient;
use Resend\Contracts\Client as ResendClientContract;
use Resend\Laravel\Exceptions\ApiKeyIsMissing;
use Resend\Transporters\HttpTransporter;
use Resend\ValueObjects\ApiKey;
use Resend\ValueObjects\Transporter\BaseUri;
use Resend\ValueObjects\Transporter\Headers;
use Shared\Infrastructure\Mail\CircuitBreakerTransport;
use Shared\Infrastructure\Resilience\CircuitBreaker\CircuitBreakerInterface;
use Symfony\Component\Mailer\Transport\TransportInterface;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        /*
         * The Resend SDK's default client is `new GuzzleClient()` with no
         * timeout, which means an unbounded HTTP call. Rebind the singleton
         * with explicit connect/total timeouts; the mail transport below wraps
         * it in the circuit breaker.
         */
        $this->app->singleton(ResendClientContract::class, static function (): ResendClientContract {
            $apiKey = config('resend.api_key') ?? config('services.resend.key');

            if (! is_string($apiKey) || $apiKey === '') {
                throw ApiKeyIsMissing::create();
            }

            return new ResendClient(new HttpTransporter(
                new GuzzleClient([
                    'timeout' => 10,
                    'connect_timeout' => 5,
                ]),
                BaseUri::from((string) (getenv('RESEND_BASE_URL') ?: 'api.resend.com')),
                Headers::withAuthorization(ApiKey::from($apiKey)),
            ));
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        /*
         * ONE outbound mail path: the `resend` mailer is backed by the native
         * Laravel Resend transport, the SDK's timed client above, and the
         * circuit breaker. Notifications, Mailables and MailInterface all use
         * this transport, so the protection cannot be forgotten per caller.
         */
        Mail::extend('resend', static fn (array $config = []): TransportInterface => new CircuitBreakerTransport(
            new ResendTransport(app(ResendClientContract::class)),
            app(CircuitBreakerInterface::class),
        ));

        // N+1 is a crash in dev, a logged warning in prod (backend skill §4).
        // `shouldBeStrict(false)` disables lazy-loading prevention, and Laravel
        // only invokes the violation callback while prevention is enabled. Keep
        // prevention on and let the callback replace the throw in production.
        Model::shouldBeStrict(! $this->app->isProduction());
        Model::preventLazyLoading();

        if ($this->app->isProduction()) {
            Model::handleLazyLoadingViolationUsing(function ($model, $relation): void {
                logger()->warning('Lazy loading ['.$relation.'] on ['.get_class($model).']');
            });

            // Belt-and-braces for Railway: even if a request reaches PHP
            // without X-Forwarded-Proto (queue worker, health probe, a proxy
            // change), every generated URL stays on HTTPS. Mixed content is
            // therefore impossible, and CSP `'self'` keeps matching @vite,
            // font preloads and redirects.
            URL::forceScheme('https');
        }

        // Auth audit + queued security email, one subscription for framework
        // and Fortify events. The subscriber is the only place that knows the
        // event-to-activity mapping.
        Event::subscribe(AuthEventSubscriber::class);

        // A local shell must not wipe a hosted database just because .env
        // points at it.
        if ($this->app->runningInConsole()) {
            Event::listen(CommandStarting::class, GuardDestructiveCommandsOnRemoteDatabase::class);
        }
    }
}
