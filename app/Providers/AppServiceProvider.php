<?php

declare(strict_types=1);

namespace App\Providers;

use App\Listeners\AuthEventSubscriber;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // N+1 is a crash in dev, a logged warning in prod (backend skill §4).
        Model::shouldBeStrict(! $this->app->isProduction());

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
    }
}
