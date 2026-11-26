<?php

declare(strict_types=1);

namespace Modules\ActivityLog\Exports;

use App\Models\User;
use Modules\ActivityLog\Data\ActivityFilterData;
use Modules\ActivityLog\Queries\ActivityListQuery;
use Shared\Support\ActivityProperties;
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
     * The max id is captured before the first row is read so rows inserted
     * during the export cannot shift the offset window; the limit is a hard
     * cap, not only the controller's pre-count.
     *
     * @return iterable<int, array<string, string|int|float>>
     */
    public function rows(int $limit = self::MAX_ROWS): iterable
    {
        $maxId = $this->query->maxId($this->filters);

        if ($maxId === null) {
            return;
        }

        $entries = $this->query
            ->exportBuilder($this->filters)
            ->withCauser()
            ->where('id', '<=', $maxId)
            ->limit($limit);

        foreach ($entries->lazy(500) as $entry) {
            $causer = $entry->causer;
            $properties = $entry->properties?->all() ?? [];

            yield [
                'created_at' => SpreadsheetCell::sanitise($entry->created_at?->toIso8601String() ?? ''),
                'log_name' => SpreadsheetCell::sanitise((string) ($entry->log_name ?? 'default')),
                'event' => SpreadsheetCell::sanitise((string) ($entry->event ?? '')),
                'description' => SpreadsheetCell::sanitise((string) $entry->description),
                'causer' => SpreadsheetCell::sanitise(
                    ActivityProperties::causerName(
                        $properties,
                        $causer instanceof User ? $causer->name : null,
                    ) ?? 'System',
                ),
                'subject' => SpreadsheetCell::sanitise(
                    $entry->subject_type === null ? '' : class_basename($entry->subject_type),
                ),
            ];
        }
    }
}
