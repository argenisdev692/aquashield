<?php

declare(strict_types=1);

namespace Modules\Users\Tests\Support;

use App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
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
        'VIEW_ANY_USER',
        'VIEW_USER',
        'CREATE_USER',
        'UPDATE_USER',
        'DELETE_USER',
        'RESTORE_USER',
        'BULK_DELETE_USER',
        'BULK_RESTORE_USER',
        'EXPORT_USER',
        'ASSIGN_ROLES_USER',
        'ASSIGN_PERMISSIONS_USER',
    ];

    public static function seedPermissions(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (self::PERMISSIONS as $name) {
            Permission::findOrCreate($name, 'web');
        }

        Role::findOrCreate('SUPER_ADMIN', 'web')
            ->syncPermissions(Permission::all());

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public static function superAdmin(): User
    {
        $user = User::factory()->create();
        $user->assignRole('SUPER_ADMIN');

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
