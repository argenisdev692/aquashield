<?php

declare(strict_types=1);

namespace Modules\AccessControl\Queries;

use App\Models\Permission;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Modules\AccessControl\Data\PermissionFilterData;
use Shared\Support\SearchPattern;

/**
 * The single permission list/export query.
 *
 * The group filter uses an explicit suffix comparison (`substr`), not
 * `LIKE '%_GROUP'`, because `_` is a LIKE wildcard and would match a one
 * character separator anywhere in the name.
 */
final class PermissionListQuery
{
    /**
     * @return Builder<Permission>
     */
    public function builder(PermissionFilterData $filters): Builder
    {
        $search = (string) $filters->search;

        $query = Permission::query()
            ->withCount(['roles', 'users'])
            ->when(
                $search !== '',
                static function (Builder $query) use ($search): Builder {
                    SearchPattern::apply($query, 'name', $search);

                    return $query;
                },
            )
            ->when(
                $filters->type === 'system',
                static fn (Builder $query): Builder => $query->where('is_system', true),
            )
            ->when(
                $filters->type === 'custom',
                static fn (Builder $query): Builder => $query->where('is_system', false),
            )
            ->when(
                $filters->group !== null,
                static function (Builder $query) use ($filters): Builder {
                    $group = (string) $filters->group;

                    /*
                     * PostgreSQL's substr() treats a negative start as "from
                     * character 1", not "from the end" (MySQL/SQLite do the
                     * latter). Compute the positive start instead, so the same
                     * predicate returns the suffix on every supported engine.
                     */
                    return $query->whereRaw(
                        'substr(name, length(name) - ? + 1) = ?',
                        [strlen($group), $group],
                    );
                },
            )
            ->when(
                $filters->role !== null,
                static fn (Builder $query): Builder => $query->whereHas(
                    'roles',
                    static fn (Builder $roles): Builder => $roles->where('name', $filters->role),
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
     * @return LengthAwarePaginator<int, Permission>
     */
    public function paginate(PermissionFilterData $filters): LengthAwarePaginator
    {
        return $this->builder($filters)
            ->paginate(perPage: $filters->perPage, page: $filters->page)
            ->withQueryString();
    }

    public function count(PermissionFilterData $filters): int
    {
        return $this->builder($filters)->count();
    }
}
