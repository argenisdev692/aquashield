<?php

declare(strict_types=1);

namespace Modules\Backups\Application\Actions;

use Modules\Backups\Application\Ports\BackupArchiveStore;
use Modules\Backups\Domain\Exceptions\BackupNotFound;
use Modules\Backups\Domain\Models\BackupArchive;
use Modules\Backups\Domain\Models\BackupInventory;
use Modules\Backups\Domain\Services\BackupLifecycleGuard;
use Shared\Domain\Ports\AuditPort;
use Throwable;

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

        $moved = [];

        try {
            foreach ($targets as $target) {
                $this->archives->restore($target);
                $moved[] = $target;
            }
        } catch (Throwable $exception) {
            $this->revertRestore($moved);
            throw $exception;
        }

        // Audit only a batch that fully succeeded: an entry for a restore that
        // was reverted would be a false record.
        foreach ($targets as $target) {
            $this->audit->log('backup.restored', null, [
                'disk' => $target->disk,
                'filename' => $target->filename,
                'size_bytes' => $target->sizeBytes,
            ], $causer, 'backups');
        }
    }

    /**
     * Best-effort compensation for a batch that failed halfway. The restored
     * entry is re-resolved by disk + filename because `restore()` returns void
     * and the archive now lives at the active path.
     *
     * @param list<BackupArchive> $moved
     */
    private function revertRestore(array $moved): void
    {
        if ($moved === []) {
            return;
        }

        $active = BackupInventory::fromList($this->archives->active());

        foreach (array_reverse($moved) as $archive) {
            $entry = $active->findByFilename($archive->disk, $archive->filename);

            if ($entry === null) {
                continue;
            }

            try {
                $this->archives->quarantine($entry);
            } catch (Throwable $rollbackFailure) {
                report($rollbackFailure);
            }
        }
    }
}
