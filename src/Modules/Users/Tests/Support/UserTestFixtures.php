<?php

declare(strict_types=1);

namespace Modules\Users\Tests\Support;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Shared\Domain\Enums\Permission as PermissionName;
use Shared\Domain\Enums\Role as RoleName;
use Spatie\Permission\PermissionRegistrar;

/**
 * Shared setup for the Users feature tests.
 *
 * Kept as a class (not global functions) so three test files can use the same
 * fixture without redeclaring Pest helpers in the global namespace.
 */
final class UserTestFixtures
{
    /**
     * @var list<string>
     */
    public const array PERMISSIONS = [
        PermissionName::ViewAnyUser->value,
        PermissionName::ViewUser->value,
        PermissionName::CreateUser->value,
        PermissionName::UpdateUser->value,
        PermissionName::DeleteUser->value,
        PermissionName::RestoreUser->value,
        PermissionName::BulkDeleteUser->value,
        PermissionName::BulkRestoreUser->value,
        PermissionName::ExportUser->value,
        PermissionName::AssignRolesUser->value,
        PermissionName::AssignPermissionsUser->value,
    ];

    public static function seedPermissions(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (self::PERMISSIONS as $name) {
            Permission::findOrCreate($name, 'web');
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

    /**
     * A user holding every Users permission but not the SUPER_ADMIN role.
     *
     * The route group's `can:manage-staff` gate must still return 403: the
     * capability is a role-derived Gate, not a permission bundle.
     */
    public static function delegatedAdmin(): User
    {
        $user = User::factory()->create();
        $user->givePermissionTo(self::PERMISSIONS);

        return $user;
    }

    /**
     * @param list<string> $permissions
     */
    public static function role(string $name, array $permissions): Role
    {
        $role = Role::findOrCreate($name, 'web');
        $role->syncPermissions($permissions);

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $role;
    }

    public static function userWithRole(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }
}
