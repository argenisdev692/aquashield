<?php

declare(strict_types=1);

namespace Modules\Backups\Application\Actions;

use Illuminate\Contracts\Auth\Authenticatable;
use Modules\Backups\Jobs\RunDatabaseBackupJob;
use Shared\Domain\Ports\AuditPort;
use Throwable;

final readonly class RunDatabaseBackupAction
{
    public function __construct(
        private AuditPort $audit,
    ) {}

    public function execute(?object $causer): void
    {
        $requestedBy = $causer instanceof Authenticatable
            ? (int) $causer->getAuthIdentifier()
            : null;

        // The dump can hold a worker for 15 minutes; the heavy queue isolates
        // it from invitations and password resets (worker-heavy in supervisord).
        RunDatabaseBackupJob::dispatch($requestedBy)->onQueue(RunDatabaseBackupJob::QUEUE);

        // The job is already queued; a failing audit write is reported, not
        // surfaced as a 500 for a request that successfully scheduled a run.
        try {
            $this->audit->log('backup.run_requested', null, [
                'requested_by' => $requestedBy,
            ], $causer, 'backups');
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
