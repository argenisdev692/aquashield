<?php

declare(strict_types=1);

namespace Modules\AccessControl\Queries;

use App\Models\Permission;
use App\Models\Role;
use Modules\AccessControl\Data\FilterOptionsData;
use Modules\AccessControl\Support\PermissionName;

/**
 * The values the two filter bars need. Two queries, no per-row lookup.
 */
final class FilterOptionsQuery
{
    public function execute(): FilterOptionsData
    {
        /** @var list<string> $permissionNames */
        $permissionNames = Permission::query()
            ->orderBy('name')
            ->pluck('name')
            ->map(static fn (mixed $name): string => (string) $name)
            ->values()
            ->all();

        /** @var list<string> $roles */
        $roles = Role::query()
            ->orderBy('name')
            ->pluck('name')
            ->map(static fn (mixed $name): string => (string) $name)
            ->values()
            ->all();

        $groups = array_values(array_unique(array_map(
            static fn (string $name): string => PermissionName::group($name),
            $permissionNames,
        )));
        sort($groups);

        return new FilterOptionsData(
            permissions: $permissionNames,
            roles: $roles,
            groups: $groups,
        );
    }
}
