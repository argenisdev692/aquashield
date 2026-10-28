<?php

declare(strict_types=1);

namespace Modules\Backups\Application\Actions;

use Modules\Backups\Application\Repositories\BackupArchiveStore;
use Modules\Backups\Domain\Exceptions\BackupNotFound;
use Modules\Backups\Domain\Models\BackupArchive;
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
        $active = $this->archives->active();
        $targets = $this->resolve($uuids, $active);

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

    /**
     * @param list<string> $uuids
     * @param list<BackupArchive> $active
     * @return list<BackupArchive>
     */
    private function resolve(array $uuids, array $active): array
    {
        $indexed = [];

        foreach ($active as $archive) {
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
