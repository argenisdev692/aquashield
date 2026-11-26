<?php

declare(strict_types=1);

namespace Modules\CompanyData\Providers;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Modules\CompanyData\Console\ProvisionCompanyDataCommand;

final class CompanyServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                ProvisionCompanyDataCommand::class,
            ]);
        }

        // One authorisation source: these abilities are the API transport's
        // view of the same Spatie permissions the web routes use.
        Gate::define(
            'view-company-data',
            static fn (User $user): bool => $user->can('VIEW_COMPANY_DATA'),
        );

        Gate::define(
            'update-company-data',
            static fn (User $user): bool => $user->can('UPDATE_COMPANY_DATA'),
        );

        $this->loadRoutesFrom(__DIR__.'/../Routes/web.php');
        $this->loadRoutesFrom(__DIR__.'/../Routes/api.php');
    }
}
