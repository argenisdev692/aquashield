<?php

declare(strict_types=1);

namespace Modules\Users\Support;

use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * The one definition of "this account loses its access now".
 *
 * Token revocation is unconditional: a Sanctum token must not outlive the
 * lifecycle state that issued it. Server-side session rows can only be purged
 * when the session store is the database; with any other driver the second
 * layer is `EnsureAccountIsActive`, which terminates the session on its next
 * request. Keeping both halves here stops the three lifecycle actions from
 * drifting into three slightly different definitions of "revoked".
 */
final class AccessRevocation
{
    public function revoke(User $user): void
    {
        $user->tokens()->delete();

        if (config('session.driver') === 'database') {
            DB::table((string) config('session.table', 'sessions'))
                ->where('user_id', $user->getKey())
                ->delete();
        }
    }
}
