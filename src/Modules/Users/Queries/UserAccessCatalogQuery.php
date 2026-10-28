<?php

declare(strict_types=1);

namespace Modules\Users\Queries;

use Modules\Users\Data\RoleOptionData;
use Modules\Users\Data\UserAccessCatalogData;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Read side for the assignment screen: roles with their permission names, and
 * the flat permission list.
 *
 * No custom cache: spatie/laravel-permission already caches the permission
 * registry for 24 hours and flushes it on every role/permission change. A
 * second cache here would need a second invalidation path and would drift.
 */
final class UserAccessCatalogQuery
{
    public function execute(): UserAccessCatalogData
    {
        $roles = Role::query()
            ->with('permissions:id,name')
            ->orderBy('name')
            ->get()
            ->map(static fn (Role $role): RoleOptionData => new RoleOptionData(
                name: (string) $role->name,
                permissions: $role->permissions
                    ->pluck('name')
                    ->map(static fn (mixed $name): string => (string) $name)
                    ->values()
                    ->all(),
            ))
            ->values()
            ->all();

        $permissions = Permission::query()
            ->orderBy('name')
            ->pluck('name')
            ->map(static fn (mixed $name): string => (string) $name)
            ->values()
            ->all();

        return new UserAccessCatalogData($roles, $permissions);
    }
}
