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

        $moved = [];

        try {
            foreach ($targets as $target) {
                $this->archives->quarantine($target);
                $moved[] = $target;
            }
        } catch (Throwable $exception) {
            $this->revertQuarantine($moved);
            throw $exception;
        }

        // Audit only a batch that fully succeeded: an entry for a move that was
        // rolled back would be a false record.
        foreach ($targets as $target) {
            $this->audit->log('backup.quarantined', null, [
                'disk' => $target->disk,
                'filename' => $target->filename,
                'size_bytes' => $target->sizeBytes,
            ], $causer, 'backups');
        }
    }

    /**
     * Best-effort compensation for a batch that failed halfway. The quarantine
     * entry is re-resolved by disk + filename because `quarantine()` returns
     * void and the moved archive now lives at a different storage path.
     *
     * @param list<BackupArchive> $moved
     */
    private function revertQuarantine(array $moved): void
    {
        if ($moved === []) {
            return;
        }

        $quarantined = BackupInventory::fromList($this->archives->trashed());

        foreach (array_reverse($moved) as $archive) {
            $entry = $quarantined->findByFilename($archive->disk, $archive->filename);

            if ($entry === null) {
                continue;
            }

            try {
                $this->archives->restore($entry);
            } catch (Throwable $rollbackFailure) {
                report($rollbackFailure);
            }
        }
    }
}
