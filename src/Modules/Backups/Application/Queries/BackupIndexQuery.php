<?php

declare(strict_types=1);

namespace Modules\Backups\Application\Queries;

use DateTimeImmutable;
use DateTimeZone;
use Modules\Backups\Application\Dtos\BackupPage;
use Modules\Backups\Application\Ports\BackupArchiveStore;
use Modules\Backups\Data\BackupArchiveData;
use Modules\Backups\Data\BackupFilterData;
use Modules\Backups\Domain\Models\BackupArchive;
use Modules\Backups\Domain\Models\BackupInventory;

/**
 * Reads the archive inventory, filters and sorts it in memory, then paginates.
 *
 * The inventory is a filesystem listing, not a table: spatie retains a few
 * dozen dumps after cleanup, so an in-memory pass is cheaper and more honest
 * than mirroring the files into a second source of truth.
 */
final readonly class BackupIndexQuery
{
    public function __construct(
        private BackupArchiveStore $archives,
    ) {}

    public function execute(BackupFilterData $filters): BackupPage
    {
        [$active, $trashed] = $this->inventories($filters);
        $rows = $this->filtered($filters, $active, $trashed);
        $total = count($rows);
        $lastPage = max(1, (int) ceil($total / $filters->perPage));
        $page = min($filters->page, $lastPage);

        return new BackupPage(
            items: $this->map(
                array_slice($rows, ($page - 1) * $filters->perPage, $filters->perPage),
                $active,
            ),
            total: $total,
            page: $page,
            perPage: $filters->perPage,
            lastPage: $lastPage,
        );
    }

    /**
     * Every matching archive, unpaginated — the export path.
     *
     * @return list<BackupArchiveData>
     */
    public function all(BackupFilterData $filters): array
    {
        [$active, $trashed] = $this->inventories($filters);

        return $this->map($this->filtered($filters, $active, $trashed), $active);
    }

    public function count(BackupFilterData $filters): int
    {
        [$active, $trashed] = $this->inventories($filters);

        return count($this->filtered($filters, $active, $trashed));
    }

    /**
     * One archive by UUID, active or quarantined — the Show page contract.
     */
    public function find(string $uuid): ?BackupArchiveData
    {
        $active = $this->archives->active();
        $trashed = $this->archives->trashed();
        $newest = BackupInventory::fromList($active)->newest()?->uuid;
        $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));

        foreach ([...$active, ...$trashed] as $archive) {
            if ($archive->uuid === $uuid) {
                return BackupArchiveData::fromArchive($archive, $newest === $archive->uuid, $now);
            }
        }

        return null;
    }

    /**
     * One read per inventory per request. The active list is always needed
     * (it also answers "is this the newest archive?"); the quarantine list is
     * only fetched when the filter can actually show it.
     *
     * @return array{0: list<BackupArchive>, 1: list<BackupArchive>}
     */
    private function inventories(BackupFilterData $filters): array
    {
        $active = $this->archives->active();
        $trashed = $filters->status === 'active' ? [] : $this->archives->trashed();

        return [$active, $trashed];
    }

    /**
     * @param list<BackupArchive> $active
     * @param list<BackupArchive> $trashed
     * @return list<BackupArchive>
     */
    private function filtered(BackupFilterData $filters, array $active, array $trashed): array
    {
        $rows = match ($filters->status) {
            'trashed' => $trashed,
            'all' => [...$active, ...$trashed],
            default => $active,
        };

        $needle = $filters->search === null ? null : strtolower($filters->search);

        $rows = array_values(array_filter(
            $rows,
            function (BackupArchive $archive) use ($filters, $needle): bool {
                if ($needle !== null && $needle !== '') {
                    $matches = str_contains(strtolower($archive->filename), $needle)
                        || str_contains(strtolower($archive->disk), $needle);

                    if (! $matches) {
                        return false;
                    }
                }

                if ($filters->disk !== null && $archive->disk !== $filters->disk) {
                    return false;
                }

                $takenOn = $archive->takenAt->format('Y-m-d');

                if ($filters->dateFrom !== null && $takenOn < $filters->dateFrom) {
                    return false;
                }

                return $filters->dateTo === null || $takenOn <= $filters->dateTo;
            },
        ));

        usort($rows, function (BackupArchive $a, BackupArchive $b) use ($filters): int {
            $result = match ($filters->sortField) {
                'filename' => strcasecmp($a->filename, $b->filename),
                'size_bytes' => $a->sizeBytes <=> $b->sizeBytes,
                'disk' => strcasecmp($a->disk, $b->disk),
                default => $a->takenAt <=> $b->takenAt,
            };

            if ($result === 0) {
                return strcasecmp($a->filename, $b->filename);
            }

            return $filters->sortOrder === -1 ? -$result : $result;
        });

        return $rows;
    }

    /**
     * @param list<BackupArchive> $rows
     * @param list<BackupArchive> $active
     * @return list<BackupArchiveData>
     */
    private function map(array $rows, array $active): array
    {
        $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $newest = BackupInventory::fromList($active)->newest()?->uuid;

        return array_map(
            static fn (BackupArchive $archive): BackupArchiveData => BackupArchiveData::fromArchive(
                $archive,
                $newest !== null && $archive->uuid === $newest,
                $now,
            ),
            $rows,
        );
    }
}
