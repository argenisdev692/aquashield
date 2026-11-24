<?php

declare(strict_types=1);

namespace Modules\Backups\Application\Actions;

use Modules\Backups\Application\Ports\BackupArchiveStore;
use Modules\Backups\Domain\Exceptions\BackupNotFound;
use Modules\Backups\Domain\Models\BackupInventory;
use Modules\Backups\Domain\Services\BackupLifecycleGuard;
use Shared\Domain\Ports\AuditPort;

/**
 * Removes archives from the active destination by moving them to quarantine.
 *
 * There is no database transaction: no multi-row write happens here. The file
 * move is the operation; the audit entry follows it, and a failure to audit
 * must not resurrect a file that is already out of the active set.
 */
final readonly class DeleteBackupsAction
{
    public function __construct(
        private BackupArchiveStore $archives,
        private BackupLifecycleGuard $guard,
        private AuditPort $audit,
    ) {}

    /**
     * @param list<string> $uuids
     *
     * @throws BackupNotFound
     */
    public function execute(array $uuids, ?object $causer): void
    {
        $active = BackupInventory::fromList($this->archives->active());
        $targets = $active->select($uuids);

        // Validate the whole batch before touching storage: a partial delete is
        // worse than a refused one.
        foreach ($targets as $target) {
            $this->guard->assertDeletable($target, $active);
        }

        foreach ($targets as $target) {
            $this->archives->quarantine($target);

            $this->audit->log('backup.quarantined', null, [
                'disk' => $target->disk,
                'filename' => $target->filename,
                'size_bytes' => $target->sizeBytes,
            ], $causer, 'backups');
        }
    }
}
