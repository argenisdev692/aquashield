<?php

declare(strict_types=1);

namespace Modules\Backups\Domain\Services;

use Modules\Backups\Domain\Exceptions\BackupDeletionRefused;
use Modules\Backups\Domain\Exceptions\BackupRestoreConflict;
use Modules\Backups\Domain\Models\BackupArchive;

/**
 * The two rules a backup panel must never violate.
 *
 * 1. The newest active archive is not removable: the panel cannot leave the
 *    application without a recent recovery point.
 * 2. A quarantined archive is only restorable when its destination path is
 *    free: restoring must never overwrite an archive taken later.
 *
 * Pure on purpose — no framework, no storage, no clock. That is what makes the
 * guard testable without a database or a disk, and it is the test that keeps
 * `Domain/` a layer instead of a folder.
 */
final readonly class BackupLifecycleGuard
{
    /**
     * @param list<BackupArchive> $active
     *
     * @throws BackupDeletionRefused
     */
    public function assertDeletable(BackupArchive $target, array $active): void
    {
        $newest = $this->newest($active);

        if ($newest !== null && $newest->isSameArchive($target)) {
            throw BackupDeletionRefused::newest($target->filename);
        }
    }

    /**
     * @param list<BackupArchive> $active
     *
     * @throws BackupRestoreConflict
     */
    public function assertRestorable(BackupArchive $target, array $active): void
    {
        foreach ($active as $archive) {
            if ($archive->disk === $target->disk && $archive->filename === $target->filename) {
                throw BackupRestoreConflict::filenameExists($target->filename);
            }
        }
    }

    /**
     * @param list<BackupArchive> $active
     */
    private function newest(array $active): ?BackupArchive
    {
        $newest = null;

        foreach ($active as $archive) {
            if ($newest === null || $archive->takenAt > $newest->takenAt) {
                $newest = $archive;
            }
        }

        return $newest;
    }
}
