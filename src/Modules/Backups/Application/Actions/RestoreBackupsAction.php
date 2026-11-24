<?php

declare(strict_types=1);

namespace Modules\Backups\Application\Actions;

use Modules\Backups\Application\Ports\BackupArchiveStore;
use Modules\Backups\Domain\Exceptions\BackupNotFound;
use Modules\Backups\Domain\Models\BackupInventory;
use Modules\Backups\Domain\Services\BackupLifecycleGuard;
use Shared\Domain\Ports\AuditPort;

/**
 * Restores quarantined archives to the active destination.
 *
 * A restore is refused when an active archive already occupies the target
 * filename: overwriting the newer dump is data loss, not a restore.
 */
final readonly class RestoreBackupsAction
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
        $quarantined = BackupInventory::fromList($this->archives->trashed());
        $active = BackupInventory::fromList($this->archives->active());

        $targets = $quarantined->select($uuids);

        foreach ($targets as $target) {
            $this->guard->assertRestorable($target, $active);
        }

        foreach ($targets as $target) {
            $this->archives->restore($target);

            $this->audit->log('backup.restored', null, [
                'disk' => $target->disk,
                'filename' => $target->filename,
                'size_bytes' => $target->sizeBytes,
            ], $causer, 'backups');
        }
    }
}
