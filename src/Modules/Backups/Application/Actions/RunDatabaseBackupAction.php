<?php

declare(strict_types=1);

namespace Modules\Backups\Application\Actions;

use Illuminate\Contracts\Auth\Authenticatable;
use Modules\Backups\Jobs\RunDatabaseBackupJob;
use Shared\Domain\Ports\AuditPort;

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

        RunDatabaseBackupJob::dispatch($requestedBy);

        $this->audit->log('backup.run_requested', null, [
            'requested_by' => $requestedBy,
        ], $causer, 'backups');
    }
}
