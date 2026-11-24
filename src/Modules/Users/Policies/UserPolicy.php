<?php

declare(strict_types=1);

namespace Modules\Users\Policies;

use App\Models\User;
use Modules\Users\Support\AccessCeiling;
use Shared\Domain\Enums\Permission as PermissionName;

/**
 * Row-level and action-level rules for the Users module.
 *
 * This is the declared exception to the "no per-module policy" default: the
 * module owns a privilege hierarchy that a `permission:*` string cannot
 * express. The route middleware still answers "may this actor call
 * UPDATE_USER?"; this policy answers "may this actor touch THIS row, and may
 * they see or take this row's access away?".
 */
final readonly class UserPolicy
{
    public function __construct(private AccessCeiling $ceiling) {}

    public function view(User $actor, User $target): bool
    {
        return $actor->can(PermissionName::ViewUser->value)
            && $this->ceiling->canManage($actor, $target);
    }

    public function create(User $actor): bool
    {
        return $actor->can(PermissionName::CreateUser->value);
    }

    public function update(User $actor, User $target): bool
    {
        return $actor->can(PermissionName::UpdateUser->value)
            && $this->ceiling->canManage($actor, $target);
    }

    public function delete(User $actor, User $target): bool
    {
        return $actor->can(PermissionName::DeleteUser->value)
            && ! $actor->is($target)
            && $this->ceiling->canManage($actor, $target)
            && ! $this->ceiling->isLastActiveSuperAdmin($target);
    }

    public function suspend(User $actor, User $target): bool
    {
        return $actor->can(PermissionName::UpdateUser->value)
            && ! $actor->is($target)
            && ! $target->isInvited()
            && $this->ceiling->canManage($actor, $target)
            && ! $this->ceiling->isLastActiveSuperAdmin($target);
    }

    public function activate(User $actor, User $target): bool
    {
        return $actor->can(PermissionName::UpdateUser->value)
            && $this->ceiling->canManage($actor, $target);
    }

    public function resendInvitation(User $actor, User $target): bool
    {
        return $actor->can(PermissionName::UpdateUser->value)
            && ! $target->trashed()
            && $this->ceiling->canManage($actor, $target);
    }

    public function restore(User $actor, User $target): bool
    {
        return $actor->can(PermissionName::RestoreUser->value)
            && $this->ceiling->canManage($actor, $target);
    }

    public function assignRoles(User $actor, User $target): bool
    {
        return $actor->can(PermissionName::AssignRolesUser->value)
            && ! $actor->is($target)
            && $this->ceiling->canManage($actor, $target);
    }

    public function assignPermissions(User $actor, User $target): bool
    {
        return $actor->can(PermissionName::AssignPermissionsUser->value)
            && ! $actor->is($target)
            && $this->ceiling->canManage($actor, $target);
    }
}
