<?php

declare(strict_types=1);

namespace Modules\Backups\Jobs;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Modules\Backups\Application\Ports\BackupRunLock;
use Modules\Backups\Application\Ports\DatabaseBackupRunner;
use Shared\Domain\Ports\AuditPort;
use Throwable;

/**
 * Runs the database dump off the request cycle.
 *
 * One attempt: a half-finished dump must not be retried automatically, and a
 * database dump is not idempotent when the destination filename is generated
 * per attempt. Cron owns the schedule; this job is the manual "run now".
 */
final class RunDatabaseBackupJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * A dump can hold a worker for 15 minutes. `worker-heavy` consumes this
     * queue; `worker` (the default) does not, so mail keeps moving.
     */
    public const string QUEUE = 'heavy';

    public int $tries = 1;

    public int $timeout = 900;

    public bool $failOnTimeout = true;

    public int $uniqueFor = 1800;

    public function __construct(
        public ?int $requestedBy = null,
    ) {}

    public function uniqueId(): string
    {
        return 'backups:database';
    }

    /**
     * @return list<object>
     */
    public function middleware(): array
    {
        return [
            (new WithoutOverlapping('backups:database'))
                ->expireAfter(900)
                ->dontRelease(),
        ];
    }

    public function handle(DatabaseBackupRunner $runner, AuditPort $audit, BackupRunLock $lock): void
    {
        if (! $lock->acquire()) {
            $audit->log('backup.skipped', null, [
                'requested_by' => $this->requestedBy,
            ], $this->causer(), 'backups');

            return;
        }

        try {
            $causer = $this->causer();

            $audit->log('backup.started', null, [
                'requested_by' => $this->requestedBy,
            ], $causer, 'backups');

            $runner->run();

            $audit->log('backup.completed', null, [
                'requested_by' => $this->requestedBy,
            ], $causer, 'backups');
        } finally {
            $lock->release();
        }
    }

    public function failed(Throwable $exception): void
    {
        // Only the class name crosses into the audit trail and the log line.
        // mysqldump/pg_dump errors can echo connection arguments, and those may
        // contain credentials (OWASP A04/A09).
        logger()->error('Database backup failed.', ['exception' => $exception::class]);

        app(AuditPort::class)->log('backup.failed', null, [
            'reason' => $exception::class,
            'requested_by' => $this->requestedBy,
        ], $this->causer(), 'backups');
    }

    private function causer(): ?object
    {
        return $this->requestedBy === null
            ? null
            : User::query()->find($this->requestedBy);
    }
}
