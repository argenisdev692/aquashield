<?php

declare(strict_types=1);

namespace Modules\Users\Queries;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Modules\Users\Data\UserFilterData;

/**
 * The single definition of the Users list query.
 *
 * Index and all three export formats consume this class, so a filter added here
 * cannot be forgotten in a CSV — the most common authorisation-adjacent leak in
 * this stack is an export that quietly widens the list.
 *
 * Eager loading is applied only on the paginated path; the export adds the
 * relation it actually renders. No bare `with()` without columns.
 */
final class UserListQuery
{
    /**
     * @return Builder<User>
     */
    public function builder(UserFilterData $filters): Builder
    {
        $query = User::query()
            ->when(
                $filters->search !== null && $filters->search !== '',
                static function (Builder $query) use ($filters): void {
                    $search = '%'.$filters->search.'%';

                    $query->where(static function (Builder $query) use ($search): void {
                        $query->where('first_name', 'like', $search)
                            ->orWhere('last_name', 'like', $search)
                            ->orWhere('username', 'like', $search)
                            ->orWhere('email', 'like', $search)
                            ->orWhere('phone', 'like', $search);
                    });
                },
            )
            ->when(
                $filters->role !== [],
                static fn (Builder $query): Builder => $query->role($filters->role),
            )
            ->when(
                $filters->permission !== null,
                static fn (Builder $query): Builder => $query->permission($filters->permission),
            )
            ->when(
                $filters->accountStatus !== null,
                static fn (Builder $query): Builder => $query->where('status', $filters->accountStatus),
            )
            ->when(
                $filters->status === 'trashed',
                static fn (Builder $query): Builder => $query->onlyTrashed(),
            )
            ->when(
                $filters->status === 'all',
                static fn (Builder $query): Builder => $query->withTrashed(),
            )
            ->when(
                $filters->dateFrom !== null,
                static fn (Builder $query): Builder => $query->where(
                    'created_at',
                    '>=',
                    $filters->dateFrom.' 00:00:00',
                ),
            )
            ->when(
                $filters->dateTo !== null,
                static function (Builder $query) use ($filters): Builder {
                    $nextDay = Carbon::parse($filters->dateTo)->addDay()->toDateString();

                    return $query->where('created_at', '<', $nextDay.' 00:00:00');
                },
            );

        $direction = $filters->sortOrder === -1 ? 'desc' : 'asc';

        // `name` is an accessor, not a column. Expand it to the canonical pair
        // instead of letting an orderBy on a non-column reach the database.
        if ($filters->sortField === 'name') {
            return $query
                ->orderBy('first_name', $direction)
                ->orderBy('last_name', $direction)
                ->orderBy('id');
        }

        return $query
            ->orderBy($filters->sortField, $direction)
            ->orderBy('id');
    }

    /**
     * @return LengthAwarePaginator<int, User>
     */
    public function paginate(UserFilterData $filters): LengthAwarePaginator
    {
        return $this->builder($filters)
            ->with(['roles:id,name', 'permissions:id,name'])
            ->paginate(perPage: $filters->perPage, page: $filters->page)
            ->withQueryString();
    }

    public function count(UserFilterData $filters): int
    {
        return $this->builder($filters)->count();
    }
}
