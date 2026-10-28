<?php

declare(strict_types=1);

namespace Modules\Backups\Application\Actions;

use Modules\Backups\Application\Repositories\BackupArchiveStore;
use Modules\Backups\Domain\Exceptions\BackupNotFound;
use Modules\Backups\Domain\Models\BackupArchive;
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
        $trashed = $this->archives->trashed();
        $active = $this->archives->active();

        $targets = $this->resolve($uuids, $trashed);

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

    /**
     * @param list<string> $uuids
     * @param list<BackupArchive> $trashed
     * @return list<BackupArchive>
     */
    private function resolve(array $uuids, array $trashed): array
    {
        $indexed = [];

        foreach ($trashed as $archive) {
            $indexed[$archive->uuid] = $archive;
        }

        $targets = [];

        foreach ($uuids as $uuid) {
            if (! isset($indexed[$uuid])) {
                throw BackupNotFound::forUuid($uuid);
            }

            $targets[] = $indexed[$uuid];
        }

        return $targets;
    }
}
