<?php

declare(strict_types=1);

namespace App\Providers;

use App\Console\Commands\CreateSuperAdminCommand;
use Illuminate\Support\ServiceProvider;

/**
 * App-shell console wiring. Domain commands stay in their module provider.
 */
final class ConsoleServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                CreateSuperAdminCommand::class,
            ]);
        }
    }
}
