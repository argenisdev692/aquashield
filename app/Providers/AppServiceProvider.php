<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Database\Eloquent\Model;
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
        }

    }
}
