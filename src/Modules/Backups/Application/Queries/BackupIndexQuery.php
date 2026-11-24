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
        $rows = $this->filtered($filters);
        $total = count($rows);
        $lastPage = max(1, (int) ceil($total / $filters->perPage));
        $page = min($filters->page, $lastPage);

        return new BackupPage(
            items: $this->map(
                array_slice($rows, ($page - 1) * $filters->perPage, $filters->perPage),
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
        return $this->map($this->filtered($filters));
    }

    public function count(BackupFilterData $filters): int
    {
        return count($this->filtered($filters));
    }

    /**
     * One archive by UUID, active or quarantined — the Show page contract.
     */
    public function find(string $uuid): ?BackupArchiveData
    {
        $active = $this->archives->active();
        $newest = BackupInventory::fromList($active)->newest()?->uuid;
        $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));

        foreach ([...$active, ...$this->archives->trashed()] as $archive) {
            if ($archive->uuid === $uuid) {
                return BackupArchiveData::fromArchive($archive, $newest === $archive->uuid, $now);
            }
        }

        return null;
    }

    /**
     * @return list<BackupArchive>
     */
    private function filtered(BackupFilterData $filters): array
    {
        $rows = match ($filters->status) {
            'trashed' => $this->archives->trashed(),
            'all' => [...$this->archives->active(), ...$this->archives->trashed()],
            default => $this->archives->active(),
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
     * @return list<BackupArchiveData>
     */
    private function map(array $rows): array
    {
        $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $newest = BackupInventory::fromList($this->archives->active())->newest()?->uuid;

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
