<?php

declare(strict_types=1);

namespace Modules\ActivityLog\Queries;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Modules\ActivityLog\Data\ActivityFilterData;
use Modules\ActivityLog\Models\ActivityEntry;

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
     * @return Builder<ActivityEntry>
     */
    public function builder(ActivityFilterData $filters): Builder
    {
        return ActivityEntry::query()
            ->select(self::COLUMNS)
            ->when(
                $filters->search !== null && $filters->search !== '',
                static function (Builder $query) use ($filters): void {
                    $search = '%'.$filters->search.'%';

                    $query->where(static function (Builder $query) use ($search): void {
                        $query->where('event', 'like', $search)
                            ->orWhere('description', 'like', $search)
                            ->orWhere('log_name', 'like', $search);
                    });
                },
            )
            ->when(
                $filters->logName !== null,
                static fn (Builder $query): Builder => $query->where('log_name', $filters->logName),
            )
            ->when(
                $filters->event !== null,
                static fn (Builder $query): Builder => $query->where('event', 'like', '%'.$filters->event.'%'),
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
                static fn (Builder $query): Builder => $query->where(
                    'created_at',
                    '>=',
                    $filters->dateFrom.' 00:00:00',
                ),
            )
            ->when(
                $filters->dateTo !== null,
                static function (Builder $query) use ($filters): Builder {
                    $nextDay = CarbonImmutable::parse($filters->dateTo)->addDay()->toDateString();

                    return $query->where('created_at', '<', $nextDay.' 00:00:00');
                },
            )
            ->orderBy($filters->sortField, $filters->sortOrder === -1 ? 'desc' : 'asc')
            ->orderByDesc('id');
    }

    /**
     * @return LengthAwarePaginator<int, ActivityEntry>
     */
    public function paginate(ActivityFilterData $filters): LengthAwarePaginator
    {
        return $this->builder($filters)
            ->withCauser()
            ->paginate(perPage: $filters->perPage, page: $filters->page)
            ->withQueryString();
    }

    public function count(ActivityFilterData $filters): int
    {
        return $this->builder($filters)->count();
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
