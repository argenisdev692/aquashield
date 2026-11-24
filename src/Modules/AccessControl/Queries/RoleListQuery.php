<?php

declare(strict_types=1);

namespace Modules\AccessControl\Queries;

use App\Models\Role;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Modules\AccessControl\Data\RoleData;
use Modules\AccessControl\Data\RoleFilterData;

/**
 * The single role list/export query.
 *
 * Counts are part of the builder, not the page action, so sorting by
 * `permissions_count` / `users_count` is valid on both transports and the
 * export never falls back to a lazy count per row.
 */
final class RoleListQuery
{
    /**
     * @return Builder<Role>
     */
    public function builder(RoleFilterData $filters): Builder
    {
        $query = Role::query()
            ->withCount(['permissions', 'users'])
            ->when(
                $filters->search !== null && $filters->search !== '',
                static fn (Builder $query): Builder => $query->where(
                    'name',
                    'like',
                    '%'.$filters->search.'%',
                ),
            )
            ->when(
                $filters->type === 'system',
                static fn (Builder $query): Builder => $query->whereIn('name', RoleData::systemNames()),
            )
            ->when(
                $filters->type === 'custom',
                static fn (Builder $query): Builder => $query->whereNotIn('name', RoleData::systemNames()),
            )
            ->when(
                $filters->permission !== null,
                static fn (Builder $query): Builder => $query->whereHas(
                    'permissions',
                    static fn (Builder $permissions): Builder => $permissions->where('name', $filters->permission),
                ),
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

        return $query
            ->orderBy($filters->sortField, $direction)
            ->orderBy('id');
    }

    /**
     * @return LengthAwarePaginator<int, Role>
     */
    public function paginate(RoleFilterData $filters): LengthAwarePaginator
    {
        return $this->builder($filters)
            ->paginate(perPage: $filters->perPage, page: $filters->page)
            ->withQueryString();
    }

    public function count(RoleFilterData $filters): int
    {
        return $this->builder($filters)->count();
    }
}
