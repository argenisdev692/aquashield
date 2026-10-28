<?php

declare(strict_types=1);

namespace Modules\Users\Policies;

use App\Models\User;
use Modules\Users\Support\AccessCeiling;

/**
 * Row-level and action-level rules for the Users module.
 *
 * This is the declared exception to the "no per-module policy" default: the
 * module owns a privilege hierarchy that a `permission:*` string cannot
 * express. The route middleware still answers "may this actor call
 * UPDATE_USER?"; this policy answers "may this actor touch THIS row, and may
 * they take this row's access away?".
 */
final readonly class UserPolicy
{
    public function __construct(private AccessCeiling $ceiling) {}

    public function view(User $actor, User $target): bool
    {
        return $actor->can('VIEW_USER');
    }

    public function create(User $actor): bool
    {
        return $actor->can('CREATE_USER');
    }

    public function update(User $actor, User $target): bool
    {
        return $actor->can('UPDATE_USER')
            && $this->ceiling->canManage($actor, $target);
    }

    public function delete(User $actor, User $target): bool
    {
        return $actor->can('DELETE_USER')
            && ! $actor->is($target)
            && $this->ceiling->canManage($actor, $target)
            && ! $this->ceiling->isLastSuperAdmin($target);
    }

    public function restore(User $actor, User $target): bool
    {
        return $actor->can('RESTORE_USER')
            && $this->ceiling->canManage($actor, $target);
    }

    public function assignRoles(User $actor, User $target): bool
    {
        return $actor->can('ASSIGN_ROLES_USER')
            && ! $actor->is($target)
            && $this->ceiling->canManage($actor, $target);
    }

    public function assignPermissions(User $actor, User $target): bool
    {
        return $actor->can('ASSIGN_PERMISSIONS_USER')
            && ! $actor->is($target)
            && $this->ceiling->canManage($actor, $target);
    }
}
