<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\User;
use Shared\Domain\Enums\Role as RoleName;

/**
 * The one statement of "is this an active SUPER_ADMIN?".
 *
 * Both capability Gates (`manage-access`, `manage-staff`) ask exactly this
 * question, and `AccessCeiling` separately owns the role hierarchy. Keeping the
 * gate predicate here stops the two providers from drifting — an inactive
 * account must never pass either gate.
 */
final class SuperAdminAccess
{
    public static function allows(User $user): bool
    {
        $user->loadMissing('roles:id,name');

        return $user->isActive() && $user->hasRole(RoleName::SuperAdmin->value);
    }
}
