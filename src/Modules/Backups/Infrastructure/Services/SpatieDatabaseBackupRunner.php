<?php

declare(strict_types=1);

namespace Modules\Backups\Infrastructure\Services;

use Illuminate\Support\Facades\Artisan;
use Modules\Backups\Application\Repositories\DatabaseBackupRunner;
use Modules\Backups\Domain\Exceptions\BackupRunFailed;

/**
 * Same code path as the cron entry: `backup:run --only-db`.
 *
 * Calling the command keeps one definition of "what a database backup is",
 * instead of a second programmatic assembly of BackupJob options.
 */
final readonly class SpatieDatabaseBackupRunner implements DatabaseBackupRunner
{
    public function run(): void
    {
        $exitCode = Artisan::call('backup:run', ['--only-db' => true]);

        if ($exitCode !== 0) {
            throw BackupRunFailed::exitCode($exitCode);
        }
    }
}
