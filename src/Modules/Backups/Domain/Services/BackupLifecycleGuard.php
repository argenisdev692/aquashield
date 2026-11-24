<?php

declare(strict_types=1);

namespace Modules\Backups\Domain\Services;

use Modules\Backups\Domain\Exceptions\BackupDeletionRefused;
use Modules\Backups\Domain\Exceptions\BackupRestoreConflict;
use Modules\Backups\Domain\Models\BackupArchive;
use Modules\Backups\Domain\Models\BackupInventory;

/**
 * The two rules a backup panel must never violate.
 *
 * 1. The newest active archive is not removable: the panel cannot leave the
 *    application without a recent recovery point.
 * 2. A quarantined archive is only restorable when its destination path is
 *    free: restoring must never overwrite an archive taken later.
 *
 * Pure on purpose — no framework, no storage, no clock. The "what is newest"
 * and "what did the client select" halves live on `BackupInventory`; this class
 * only decides whether the operation is allowed.
 */
final readonly class BackupLifecycleGuard
{
    /**
     * @throws BackupDeletionRefused
     */
    public function assertDeletable(BackupArchive $target, BackupInventory $active): void
    {
        if ($active->newest()?->isSameArchive($target) === true) {
            throw BackupDeletionRefused::newest($target->filename);
        }
    }

    /**
     * @throws BackupRestoreConflict
     */
    public function assertRestorable(BackupArchive $target, BackupInventory $active): void
    {
        if ($active->containsFilename($target->disk, $target->filename)) {
            throw BackupRestoreConflict::filenameExists($target->filename);
        }
    }
}
