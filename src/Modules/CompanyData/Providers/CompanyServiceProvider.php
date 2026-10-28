<?php

declare(strict_types=1);

namespace Modules\CompanyData\Providers;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Modules\CompanyData\Application\Repositories\CompanyAssetStorage;
use Modules\CompanyData\Application\Repositories\CompanyRepository;
use Modules\CompanyData\Infrastructure\Persistence\EloquentCompanyRepository;
use Modules\CompanyData\Infrastructure\Services\R2CompanyAssetStorage;

final class CompanyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(
            CompanyRepository::class,
            EloquentCompanyRepository::class,
        );

        // The module owns the asset contract; R2 is the current adapter. A test
        // or a storage migration swaps this binding, not the use cases.
        $this->app->bind(
            CompanyAssetStorage::class,
            R2CompanyAssetStorage::class,
        );
    }

    public function boot(): void
    {
        // One authorisation source: these abilities are the API transport's
        // view of the same Spatie permissions the web routes use.
        Gate::define(
            'view-company-data',
            static fn (User $user): bool => $user->getAllPermissions()
                ->contains('name', 'VIEW_COMPANY_DATA'),
        );

        Gate::define(
            'update-company-data',
            static fn (User $user): bool => $user->getAllPermissions()
                ->contains('name', 'UPDATE_COMPANY_DATA'),
        );

        $this->loadRoutesFrom(__DIR__.'/../Routes/web.php');
        $this->loadRoutesFrom(__DIR__.'/../Routes/api.php');
        $this->loadMigrationsFrom(__DIR__.'/../Infrastructure/Migrations');
    }
}
