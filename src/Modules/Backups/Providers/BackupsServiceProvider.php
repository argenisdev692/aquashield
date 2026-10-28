<?php

declare(strict_types=1);

namespace Modules\Backups\Providers;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Modules\Backups\Application\Repositories\BackupActivityLog;
use Modules\Backups\Application\Repositories\BackupArchiveStore;
use Modules\Backups\Application\Repositories\DatabaseBackupRunner;
use Modules\Backups\Infrastructure\Console\PurgeBackupQuarantineCommand;
use Modules\Backups\Infrastructure\Persistence\SpatieBackupActivityLog;
use Modules\Backups\Infrastructure\Persistence\SpatieBackupArchiveStore;
use Modules\Backups\Infrastructure\Services\SpatieDatabaseBackupRunner;

final class BackupsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Stateless adapters: each resolution is cheap and no request or job
        // state is retained, so `bind` (not `singleton`) is the correct
        // lifetime per the architecture skill's binding rule.
        $this->app->bind(BackupArchiveStore::class, SpatieBackupArchiveStore::class);
        $this->app->bind(DatabaseBackupRunner::class, SpatieDatabaseBackupRunner::class);
        $this->app->bind(BackupActivityLog::class, SpatieBackupActivityLog::class);
    }

    public function boot(): void
    {
        // The REST surface authenticates with Sanctum; the abilities below are
        // its view of the same Spatie permissions the web routes use.
        Gate::define(
            'view-any-backups',
            static fn (User $user): bool => $user->getAllPermissions()
                ->contains('name', 'VIEW_ANY_BACKUPS'),
        );

        Gate::define(
            'view-backup',
            static fn (User $user): bool => $user->getAllPermissions()
                ->contains('name', 'VIEW_BACKUPS'),
        );

        Gate::define(
            'download-backup',
            static fn (User $user): bool => $user->getAllPermissions()
                ->contains('name', 'DOWNLOAD_BACKUPS'),
        );

        $this->loadRoutesFrom(__DIR__.'/../Routes/web.php');
        $this->loadRoutesFrom(__DIR__.'/../Routes/api.php');

        if ($this->app->runningInConsole()) {
            $this->commands([PurgeBackupQuarantineCommand::class]);
        }
    }
}
