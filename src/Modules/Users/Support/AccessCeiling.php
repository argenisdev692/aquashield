<?php

declare(strict_types=1);

namespace Modules\Users\Support;

use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * The Users module's one load-bearing invariant.
 *
 * The permission system answers "may this actor perform UPDATE_USER?". It does
 * not answer "may this actor grant a capability they do not hold?" — that is
 * privilege escalation, and neither a route middleware nor a Gate policy can
 * see the requested grant set. Every write that hands out access goes through
 * this class:
 *
 *  - canManage():        the target's effective permissions must be a subset
 *                        of the actor's, so a MANAGER cannot edit an ADMIN.
 *  - assertCanGrant*():  every requested role/permission must be inside the
 *                        actor's own effective permissions.
 *  - isLastSuperAdmin(): deleting or demoting the last SUPER_ADMIN is refused,
 *                        so the installation cannot lock itself out.
 *
 * SUPER_ADMIN bypasses the subset checks but not the last-admin guard. The
 * literal is the second occurrence of the role name (the seeder is the first);
 * per the Rule of Three it moves to a Shared enum when a third consumer needs
 * it, not before.
 */
final class AccessCeiling
{
    /**
     * The role that bypasses the subset checks. The seeder is the other
     * occurrence of this name; see the class docblock for the Rule of Three
     * decision.
     */
    public const string SUPER_ADMIN = 'SUPER_ADMIN';

    public function canManage(User $actor, User $target): bool
    {
        if ($this->isSuperAdmin($actor)) {
            return true;
        }

        return $this->effectivePermissions($target)
            ->diff($this->effectivePermissions($actor))
            ->isEmpty();
    }

    public function isLastSuperAdmin(User $target): bool
    {
        if (! $this->isSuperAdmin($target)) {
            return false;
        }

        return ! User::query()
            ->role(self::SUPER_ADMIN)
            ->whereKeyNot($target->getKey())
            ->exists();
    }

    /**
     * @param list<string> $roles
     *
     * @throws ValidationException
     */
    public function assertCanGrantRoles(User $actor, array $roles): void
    {
        $roles = array_values(array_unique($roles));

        if ($roles === [] || $this->isSuperAdmin($actor)) {
            return;
        }

        $roleModels = Role::query()
            ->whereIn('name', $roles)
            ->with('permissions:id,name')
            ->get();

        if ($roleModels->count() !== count($roles)) {
            throw ValidationException::withMessages([
                'roles' => 'One or more roles no longer exist.',
            ]);
        }

        /** @var Collection<int, string> $granted */
        $granted = $roleModels
            ->flatMap(static fn (Role $role): Collection => $role->permissions->pluck('name'))
            ->map(static fn (mixed $name): string => (string) $name)
            ->unique()
            ->values();

        $this->assertWithinCeiling($actor, $granted, 'roles');
    }

    /**
     * @param list<string> $permissions
     *
     * @throws ValidationException
     */
    public function assertCanGrantPermissions(User $actor, array $permissions): void
    {
        $permissions = array_values(array_unique($permissions));

        if ($permissions === [] || $this->isSuperAdmin($actor)) {
            return;
        }

        $existing = Permission::query()->whereIn('name', $permissions)->count();

        if ($existing !== count($permissions)) {
            throw ValidationException::withMessages([
                'permissions' => 'One or more permissions no longer exist.',
            ]);
        }

        $this->assertWithinCeiling($actor, collect($permissions), 'permissions');
    }

    /**
     * @param Collection<int, string> $requested
     *
     * @throws ValidationException
     */
    private function assertWithinCeiling(User $actor, Collection $requested, string $field): void
    {
        $missing = $requested->diff($this->effectivePermissions($actor))->values();

        if ($missing->isNotEmpty()) {
            throw ValidationException::withMessages([
                $field => 'You cannot grant permissions you do not hold: '.$missing->implode(', ').'.',
            ]);
        }
    }

    /**
     * @return Collection<int, string>
     */
    private function effectivePermissions(User $user): Collection
    {
        $user->loadMissing([
            'roles:id,name',
            'roles.permissions:id,name',
            'permissions:id,name',
        ]);

        /** @var Collection<int, string> $names */
        $names = $user->getAllPermissions()
            ->pluck('name')
            ->map(static fn (mixed $name): string => (string) $name)
            ->unique()
            ->values();

        return $names;
    }

    public function isSuperAdmin(User $user): bool
    {
        $user->loadMissing('roles:id,name');

        return $user->hasRole(self::SUPER_ADMIN);
    }
}
