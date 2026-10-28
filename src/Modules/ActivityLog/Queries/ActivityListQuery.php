<?php

declare(strict_types=1);

namespace Modules\ActivityLog\Queries;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Modules\ActivityLog\Data\ActivityFilterData;
use Modules\ActivityLog\Models\ActivityEntry;

final class ActivityListQuery
{
    /**
     * @return Builder<ActivityEntry>
     */
    public function builder(ActivityFilterData $filters): Builder
    {
        return ActivityEntry::query()
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
                $filters->dateFrom !== null,
                static fn (Builder $query): Builder => $query->whereDate('created_at', '>=', $filters->dateFrom),
            )
            ->when(
                $filters->dateTo !== null,
                static fn (Builder $query): Builder => $query->whereDate('created_at', '<=', $filters->dateTo),
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
            // Explicit columns: an eager load without them pulls the whole
            // causer row on every audit page.
            ->with('causer:id,first_name,last_name')
            ->paginate(perPage: $filters->perPage, page: $filters->page)
            ->withQueryString();
    }

    public function count(ActivityFilterData $filters): int
    {
        return $this->builder($filters)->count();
    }
}
