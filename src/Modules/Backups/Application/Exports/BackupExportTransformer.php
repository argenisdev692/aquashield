<?php

declare(strict_types=1);

namespace Modules\Backups\Application\Exports;

use Modules\Backups\Application\Queries\BackupIndexQuery;
use Modules\Backups\Data\BackupFilterData;

/**
 * Projects the same filtered inventory the index returns into export rows.
 *
 * The export must respect the list filters; that is why it is built from the
 * index query instead of re-querying the disks with its own rules.
 */
final readonly class BackupExportTransformer
{
    public const int MAX_ROWS = 10_000;

    public function __construct(
        private BackupFilterData $filters,
        private BackupIndexQuery $query,
    ) {}

    /**
     * @return array<string, string>
     */
    public function columns(): array
    {
        return [
            'filename' => 'Filename',
            'disk' => 'Disk',
            'taken_at' => 'Taken at',
            'size' => 'Size',
            'age_days' => 'Age (days)',
            'status' => 'Status',
        ];
    }

    /**
     * @return list<string>
     */
    public function headers(): array
    {
        return array_values($this->columns());
    }

    /**
     * @return iterable<int, array<string, string|int|float>>
     */
    public function rows(): iterable
    {
        foreach ($this->query->all($this->filters) as $row) {
            yield [
                'filename' => $this->cell($row->filename),
                'disk' => $this->cell($row->disk),
                'taken_at' => $this->cell($row->takenAt),
                'size' => $this->cell($row->sizeHuman),
                'age_days' => $row->ageDays,
                'status' => $row->isTrashed ? 'Quarantined' : 'Active',
            ];
        }
    }

    /**
     * Neutralise spreadsheet formula injection even though every value here is
     * server-generated. Defence in depth costs one comparison.
     */
    private function cell(string $value): string
    {
        return preg_match('/^[=+\-@]/', $value) === 1 ? "'".$value : $value;
    }
}
