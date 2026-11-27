<?php

declare(strict_types=1);

namespace Modules\Backups\Infrastructure\Services;

use Illuminate\Support\Facades\Artisan;
use Modules\Backups\Application\Ports\DatabaseBackupRunner;
use Modules\Backups\Domain\Exceptions\BackupRunFailed;
use Modules\Backups\Infrastructure\Support\BackupArchiveEncryption;

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
        /*
         * Fail the dump, not the deploy. A non-local environment without an
         * archive password would write every table to disk in plain text, so
         * the assertion belongs here - at the one choke point every backup
         * path goes through - and never in provider boot, where it would also
         * block package:discover, config:cache and migrate during an image
         * build that has no runtime variables.
         */
        BackupArchiveEncryption::assertConfigured();

        $exitCode = Artisan::call('backup:run', ['--only-db' => true]);

        if ($exitCode !== 0) {
            throw BackupRunFailed::exitCode($exitCode);
        }
    }
}
