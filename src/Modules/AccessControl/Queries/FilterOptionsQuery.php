<?php

declare(strict_types=1);

namespace Modules\AccessControl\Queries;

use App\Models\Permission;
use App\Models\Role;
use Modules\AccessControl\Data\FilterOptionsData;
use Modules\AccessControl\Support\PermissionName;

/**
 * The values the two filter bars need, built per screen.
 *
 * The role list only filters by permission, and the permission list only
 * filters by group or role. Shipping the whole catalogue to both pages put a
 * ~500-name array on every search/page visit that one of them never read.
 */
final class FilterOptionsQuery
{
    public function forRoles(): FilterOptionsData
    {
        return new FilterOptionsData(
            permissions: $this->permissionNames(),
            roles: [],
            groups: [],
        );
    }

    public function forPermissions(): FilterOptionsData
    {
        $permissionNames = $this->permissionNames();

        return new FilterOptionsData(
            permissions: [],
            roles: $this->roleNames(),
            groups: $this->groups($permissionNames),
        );
    }

    /**
     * @return list<string>
     */
    private function permissionNames(): array
    {
        /** @var list<string> $names */
        $names = Permission::query()
            ->orderBy('name')
            ->pluck('name')
            ->map(static fn (mixed $name): string => (string) $name)
            ->values()
            ->all();

        return $names;
    }

    /**
     * @return list<string>
     */
    private function roleNames(): array
    {
        /** @var list<string> $names */
        $names = Role::query()
            ->orderBy('name')
            ->pluck('name')
            ->map(static fn (mixed $name): string => (string) $name)
            ->values()
            ->all();

        return $names;
    }

    /**
     * @param list<string> $permissionNames
     * @return list<string>
     */
    private function groups(array $permissionNames): array
    {
        $groups = array_values(array_unique(array_map(
            static fn (string $name): string => PermissionName::group($name),
            $permissionNames,
        )));
        sort($groups);

        return $groups;
    }
}
