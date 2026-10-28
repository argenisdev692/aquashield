<?php

declare(strict_types=1);

namespace Modules\Backups\Application\Repositories;

use Modules\Backups\Domain\Models\BackupArchive;

/**
 * The storage boundary. Implementations resolve the configured disks, the
 * package backup name and the quarantine layout; callers only see archives.
 *
 * `quarantine` is the panel's delete: it moves the archive out of the active
 * destination so the operation is reversible. Only the retention command calls
 * `purge`, which is the irreversible step.
 */
interface BackupArchiveStore
{
    /**
     * @return list<BackupArchive>
     */
    public function active(): array;

    /**
     * @return list<BackupArchive>
     */
    public function trashed(): array;

    public function findActive(string $uuid): ?BackupArchive;

    public function findTrashed(string $uuid): ?BackupArchive;

    /**
     * The configured destination disks, for the filter select.
     *
     * @return list<string>
     */
    public function disks(): array;

    public function quarantine(BackupArchive $archive): void;

    public function restore(BackupArchive $archive): void;

    public function purge(BackupArchive $archive): void;

    /**
     * @return resource
     */
    public function stream(BackupArchive $archive): mixed;
}
