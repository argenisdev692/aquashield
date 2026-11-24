<?php

declare(strict_types=1);

namespace Modules\Backups\Domain\Models;

use Modules\Backups\Domain\Exceptions\BackupNotFound;

/**
 * The set of archives visible to one operation, active or quarantined.
 *
 * Three rules live here instead of in every caller:
 *
 * - `newest()` is the archive the retention guard protects. It was previously
 *   recomputed in the guard, the index query and the adapter's UUID lookup;
 *   three copies of the same rule is one copy too many.
 * - `find()` is the single UUID lookup behind the Show page and the download
 *   controller.
 * - `select()` resolves the client's UUIDs against the inventory and refuses
 *   the whole batch when one is gone, so a bulk action can never move part of
 *   a stale selection.
 */
final readonly class BackupInventory
{
    /**
     * @param list<BackupArchive> $archives
     */
    private function __construct(
        private array $archives,
    ) {}

    /**
     * @param list<BackupArchive> $archives
     */
    public static function fromList(array $archives): self
    {
        return new self($archives);
    }

    public function newest(): ?BackupArchive
    {
        $newest = null;

        foreach ($this->archives as $archive) {
            if ($newest === null || $archive->takenAt > $newest->takenAt) {
                $newest = $archive;
            }
        }

        return $newest;
    }

    public function find(string $uuid): ?BackupArchive
    {
        foreach ($this->archives as $archive) {
            if ($archive->uuid === $uuid) {
                return $archive;
            }
        }

        return null;
    }

    public function containsFilename(string $disk, string $filename): bool
    {
        foreach ($this->archives as $archive) {
            if ($archive->disk === $disk && $archive->filename === $filename) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<string> $uuids
     * @return list<BackupArchive>
     *
     * @throws BackupNotFound
     */
    public function select(array $uuids): array
    {
        $indexed = [];

        foreach ($this->archives as $archive) {
            $indexed[$archive->uuid] = $archive;
        }

        $selected = [];

        foreach ($uuids as $uuid) {
            if (! isset($indexed[$uuid])) {
                throw BackupNotFound::gone();
            }

            $selected[] = $indexed[$uuid];
        }

        return $selected;
    }
}
