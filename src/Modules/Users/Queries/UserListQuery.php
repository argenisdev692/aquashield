<?php

declare(strict_types=1);

namespace Modules\Users\Queries;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
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
        return User::query()
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
                $filters->role !== null,
                static fn (Builder $query): Builder => $query->role($filters->role),
            )
            ->when(
                $filters->permission !== null,
                static fn (Builder $query): Builder => $query->permission($filters->permission),
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
                static fn (Builder $query): Builder => $query->whereDate('created_at', '>=', $filters->dateFrom),
            )
            ->when(
                $filters->dateTo !== null,
                static fn (Builder $query): Builder => $query->whereDate('created_at', '<=', $filters->dateTo),
            )
            ->orderBy($filters->sortField, $filters->sortOrder === -1 ? 'desc' : 'asc')
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
