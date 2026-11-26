<?php

declare(strict_types=1);

namespace Modules\ActivityLog\Queries;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;
use Modules\ActivityLog\Data\ActivityFilterData;
use Modules\ActivityLog\Models\ActivityEntry;
use Throwable;

final class ActivityListQuery
{
    /**
     * Explicit read model: `attribute_changes` and `subject_id` never cross
     * this module's boundary, so they are not hydrated.
     *
     * @var list<string>
     */
    private const array COLUMNS = [
        'uuid',
        'log_name',
        'event',
        'description',
        'subject_type',
        'causer_type',
        'causer_id',
        'properties',
        'created_at',
    ];

    /**
     * The export never reads `properties` or `uuid`; hydrating them for up to
     * 10,000 rows is wasted memory.
     *
     * @var list<string>
     */
    private const array EXPORT_COLUMNS = [
        'log_name',
        'event',
        'description',
        'subject_type',
        'causer_type',
        'causer_id',
        'created_at',
    ];

    /**
     * @return Builder<ActivityEntry>
     */
    public function builder(ActivityFilterData $filters): Builder
    {
        return ActivityEntry::query()
            ->select(self::COLUMNS)
            ->when(
                $filters->search !== null && $filters->search !== '',
                static function (Builder $query) use ($filters): void {
                    // Postgres LIKE is case-sensitive; LOWER() keeps the same
                    // behaviour on SQLite (the test engine) and Postgres.
                    $search = '%'.mb_strtolower($filters->search).'%';

                    $query->where(static function (Builder $query) use ($search): void {
                        $query->whereRaw('LOWER(event) LIKE ?', [$search])
                            ->orWhereRaw('LOWER(description) LIKE ?', [$search])
                            ->orWhereRaw('LOWER(log_name) LIKE ?', [$search]);
                    });
                },
            )
            ->when(
                $filters->logName !== null,
                static fn (Builder $query): Builder => $query->where('log_name', $filters->logName),
            )
            ->when(
                $filters->event !== null,
                static fn (Builder $query): Builder => $query->whereRaw(
                    'LOWER(event) LIKE ?',
                    ['%'.mb_strtolower((string) $filters->event).'%'],
                ),
            )
            ->when(
                $filters->subjectType !== null,
                static fn (Builder $query): Builder => $query->where('subject_type', $filters->subjectType),
            )
            ->when(
                $filters->subjectId !== null,
                static fn (Builder $query): Builder => $query->where('subject_id', $filters->subjectId),
            )
            ->when(
                $filters->dateFrom !== null,
                // Range predicate, not whereDate(): the column stays bare so the
                // planner can use the created_at index (see the users migration).
                static function (Builder $query) use ($filters): Builder {
                    $from = CarbonImmutable::parse(
                        (string) $filters->dateFrom,
                        $filters->timezone ?? (string) config('app.timezone', 'UTC'),
                    )->startOfDay()->utc();

                    return $query->where('created_at', '>=', $from->toDateTimeString());
                },
            )
            ->when(
                $filters->dateTo !== null,
                static function (Builder $query) use ($filters): Builder {
                    $to = CarbonImmutable::parse(
                        (string) $filters->dateTo,
                        $filters->timezone ?? (string) config('app.timezone', 'UTC'),
                    )->startOfDay()->addDay()->utc();

                    return $query->where('created_at', '<', $to->toDateTimeString());
                },
            )
            ->orderBy($filters->sortField, $filters->sortOrder === -1 ? 'desc' : 'asc')
            ->orderByDesc('id');
    }

    /**
     * @return Builder<ActivityEntry>
     */
    public function exportBuilder(ActivityFilterData $filters): Builder
    {
        return $this->builder($filters)->select(self::EXPORT_COLUMNS);
    }

    public function maxId(ActivityFilterData $filters): ?int
    {
        // An aggregate cannot carry the list's ORDER BY on Postgres; reorder()
        // keeps the export snapshot query valid on both engines.
        $max = $this->builder($filters)->reorder()->max('id');

        return is_numeric($max) ? (int) $max : null;
    }

    /**
     * @return LengthAwarePaginator<int, ActivityEntry>
     */
    public function paginate(ActivityFilterData $filters): LengthAwarePaginator
    {
        return $this->builder($filters)
            ->withCauser()
            ->paginate(
                perPage: $filters->perPage,
                page: $filters->page,
                total: $this->total($filters),
            )
            ->withQueryString();
    }

    /**
     * The unfiltered total is the whole 7-year table; counting it on every
     * page is the module's hottest query. Cache it for 30 s and serve the
     * stale value for up to 5 min while the next request refreshes it.
     * Filtered totals stay exact and are never cached under a user-supplied
     * key. A cache outage falls back to the exact count.
     */
    public function total(ActivityFilterData $filters): int
    {
        if ($this->isFiltered($filters)) {
            return $this->count($filters);
        }

        try {
            return (int) Cache::flexible(
                'activity-log.total.v1.all',
                [30, 300],
                fn (): int => $this->count($filters),
            );
        } catch (Throwable) {
            return $this->count($filters);
        }
    }

    private function isFiltered(ActivityFilterData $filters): bool
    {
        return ($filters->search !== null && $filters->search !== '')
            || $filters->logName !== null
            || $filters->event !== null
            || $filters->subjectType !== null
            || $filters->subjectId !== null
            || $filters->dateFrom !== null
            || $filters->dateTo !== null;
    }

    public function count(ActivityFilterData $filters): int
    {
        return $this->builder($filters)->reorder()->count();
    }

    public function findByUuid(string $uuid): ?ActivityEntry
    {
        return ActivityEntry::query()
            ->select(self::COLUMNS)
            ->withCauser()
            ->where('uuid', $uuid)
            ->first();
    }
}
