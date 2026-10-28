<?php

declare(strict_types=1);

namespace Modules\ActivityLog\Exports;

use App\Models\User;
use Modules\ActivityLog\Data\ActivityFilterData;
use Modules\ActivityLog\Queries\ActivityListQuery;

/**
 * Projects the same filtered audit query the table uses. The export never
 * builds its own scope: a filtered list and its CSV must contain the same
 * rows, and that is only true if one query object feeds both.
 */
final readonly class ActivityExportTransformer
{
    public const int MAX_ROWS = 10_000;

    public function __construct(
        private ActivityFilterData $filters,
        private ActivityListQuery $query,
    ) {}

    /**
     * @return array<string, string>
     */
    public function columns(): array
    {
        return [
            'created_at' => 'Recorded',
            'log_name' => 'Log',
            'event' => 'Event',
            'description' => 'Description',
            'causer' => 'Causer',
            'subject' => 'Subject',
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
        $entries = $this->query
            ->builder($this->filters)
            ->with('causer:id,first_name,last_name');

        foreach ($entries->lazy(500) as $entry) {
            $causer = $entry->causer;

            yield [
                'created_at' => $this->cell($entry->created_at?->format('Y-m-d H:i') ?? ''),
                'log_name' => $this->cell((string) ($entry->log_name ?? 'default')),
                'event' => $this->cell((string) ($entry->event ?? '')),
                'description' => $this->cell((string) $entry->description),
                'causer' => $this->cell($causer instanceof User ? $causer->name : 'System'),
                'subject' => $this->cell(
                    $entry->subject_type === null ? '' : class_basename($entry->subject_type),
                ),
            ];
        }
    }

    /**
     * Spreadsheet formula injection defence. Audit values are server-written,
     * but a description can carry a model value that starts with `=`.
     */
    private function cell(string $value): string
    {
        return preg_match('/^[=+\-@]/', $value) === 1 ? "'".$value : $value;
    }
}
