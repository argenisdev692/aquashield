<?php

declare(strict_types=1);

namespace Modules\Backups\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\Backups\Application\Ports\BackupActivityLog;
use Modules\Backups\Application\Ports\BackupArchiveStore;
use Modules\Backups\Application\Ports\DatabaseBackupRunner;
use Modules\Backups\Infrastructure\Console\PurgeBackupQuarantineCommand;
use Modules\Backups\Infrastructure\Persistence\SpatieBackupActivityLog;
use Modules\Backups\Infrastructure\Persistence\SpatieBackupArchiveStore;
use Modules\Backups\Infrastructure\Services\SpatieDatabaseBackupRunner;
use Modules\Backups\Infrastructure\Support\BackupArchiveEncryption;

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
        // Fail the deploy, not the data: a production container without an
        // archive password would write every table to disk in plain text.
        BackupArchiveEncryption::assertConfiguredInProduction();

        $this->loadRoutesFrom(__DIR__.'/../Routes/web.php');
        $this->loadRoutesFrom(__DIR__.'/../Routes/api.php');

        if ($this->app->runningInConsole()) {
            $this->commands([PurgeBackupQuarantineCommand::class]);
        }
    }
}
