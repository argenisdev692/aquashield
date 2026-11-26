<?php

declare(strict_types=1);

namespace Modules\ActivityLog\Exports;

use App\Models\User;
use Modules\ActivityLog\Data\ActivityFilterData;
use Modules\ActivityLog\Queries\ActivityListQuery;
use Shared\Support\SpreadsheetCell;

/**
 * Projects the same filtered audit query the table uses. The export never
 * builds its own scope: a filtered list and its CSV must contain the same
 * rows, and that is only true if one query object feeds both.
 */
final readonly class ActivityExportTransformer
{
    public const int MAX_ROWS = 10_000;

    /**
     * DomPDF renders the whole document in memory before the response is sent.
     * Five hundred rows is the bound that keeps a synchronous render inside a
     * normal PHP-FPM memory budget; CSV/XLSX stay at MAX_ROWS because they
     * stream row by row.
     */
    public const int MAX_PDF_ROWS = 500;

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
            ->withCauser();

        foreach ($entries->lazy(500) as $entry) {
            $causer = $entry->causer;

            yield [
                'created_at' => SpreadsheetCell::sanitise($entry->created_at?->toIso8601String() ?? ''),
                'log_name' => SpreadsheetCell::sanitise((string) ($entry->log_name ?? 'default')),
                'event' => SpreadsheetCell::sanitise((string) ($entry->event ?? '')),
                'description' => SpreadsheetCell::sanitise((string) $entry->description),
                'causer' => SpreadsheetCell::sanitise($causer instanceof User ? $causer->name : 'System'),
                'subject' => SpreadsheetCell::sanitise(
                    $entry->subject_type === null ? '' : class_basename($entry->subject_type),
                ),
            ];
        }
    }
}
