<?php

declare(strict_types=1);

namespace Modules\Backups\Infrastructure\Console;

use Illuminate\Console\Command;
use Modules\Backups\Application\Ports\BackupRunLock;
use Modules\Backups\Application\Ports\DatabaseBackupRunner;
use Shared\Domain\Ports\AuditPort;
use Throwable;

/**
 * The scheduled entry point for a database-only dump.
 *
 * It exists so the cron and the manual job acquire the same lock; the package
 * command alone has no lock and shares a fixed temporary directory.
 * `php artisan backup:run --only-db` remains the package's raw command and is
 * deliberately not used by the schedule. It also writes the same audit events
 * as the job, so the daily run is not missing from the backup audit trail.
 */
final class RunBackupCommand extends Command
{
    protected $signature = 'backups:run';

    protected $description = 'Run a database-only backup under the shared backup lock.';

    public function handle(BackupRunLock $lock, DatabaseBackupRunner $runner, AuditPort $audit): int
    {
        if (! $lock->acquire()) {
            $audit->log('backup.skipped', null, [
                'requested_by' => null,
            ], null, 'backups');

            $this->warn('A database backup is already running; skipping this run.');

            return self::SUCCESS;
        }

        try {
            $this->info('Running database-only backup...');

            $audit->log('backup.started', null, [
                'requested_by' => null,
            ], null, 'backups');

            $runner->run();

            $audit->log('backup.completed', null, [
                'requested_by' => null,
            ], null, 'backups');

            $this->info('Database backup finished.');
        } catch (Throwable $exception) {
            $audit->log('backup.failed', null, [
                'reason' => $exception::class,
                'requested_by' => null,
            ], null, 'backups');

            throw $exception;
        } finally {
            $lock->release();
        }

        return self::SUCCESS;
    }
}
