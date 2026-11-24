<?php

declare(strict_types=1);

namespace Modules\AccessControl\Tests\Support;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Shared\Domain\Enums\Permission as PermissionName;
use Shared\Domain\Enums\Role as RoleName;
use Spatie\Permission\PermissionRegistrar;

/**
 * Instance-level setup for the AccessControl feature tests.
 *
 * The ten permission names are the module's route vocabulary; the seeded
 * catalogue is represented as `is_system = true`.
 */
final class AccessControlFixtures
{
    /** @var list<string> */
    public const array PERMISSIONS = [
        PermissionName::ViewAnyRole->value,
        PermissionName::CreateRole->value,
        PermissionName::UpdateRole->value,
        PermissionName::DeleteRole->value,
        PermissionName::ExportRole->value,
        PermissionName::ViewAnyPermission->value,
        PermissionName::CreatePermission->value,
        PermissionName::UpdatePermission->value,
        PermissionName::DeletePermission->value,
        PermissionName::ExportPermission->value,
    ];

    public static function seedPermissions(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (self::PERMISSIONS as $name) {
            Permission::query()->updateOrCreate(
                ['name' => $name, 'guard_name' => 'web'],
                ['is_system' => true],
            );
        }

        Role::findOrCreate(RoleName::SuperAdmin->value, 'web')
            ->syncPermissions(Permission::all());

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public static function superAdmin(): User
    {
        $user = User::factory()->create();
        $user->assignRole(RoleName::SuperAdmin->value);

        return $user;
    }

    public static function delegatedAdmin(): User
    {
        $user = User::factory()->create();
        $user->givePermissionTo(self::PERMISSIONS);

        return $user;
    }

    /**
     * @param list<string> $permissions
     */
    public static function customRole(string $name = 'CLAIMS_REVIEWER', array $permissions = []): Role
    {
        $role = Role::findOrCreate($name, 'web');

        if ($permissions !== []) {
            $role->syncPermissions($permissions);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $role;
    }

    public static function customPermission(string $name = 'APPROVE_CLAIM'): Permission
    {
        $permission = Permission::create([
            'name' => $name,
            'guard_name' => 'web',
            'is_system' => false,
        ]);

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $permission;
    }
}
