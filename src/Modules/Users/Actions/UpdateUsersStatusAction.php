<?php

declare(strict_types=1);

namespace Modules\Users\Actions;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Modules\Users\Data\BulkActionResultData;
use Modules\Users\Data\BulkSkipData;
use Modules\Users\Enums\UserStatus;
use Modules\Users\Support\AccessCeiling;
use Shared\Domain\Ports\AuditPort;

/**
 * Bulk activate / suspend.
 *
 * One transaction, one row lock set, one audit event per changed account. A
 * protected row (self, last active super admin, an invitation that has not been
 * consumed) is skipped with a reason instead of aborting the whole selection —
 * the administrator asked for a status change on N rows, and the response says
 * exactly how many happened.
 */
final readonly class UpdateUsersStatusAction
{
    public function __construct(
        private AuditPort $audit,
        private AccessCeiling $ceiling,
    ) {}

    /**
     * @param list<string> $uuids
     */
    public function execute(User $actor, array $uuids, UserStatus $status): BulkActionResultData
    {
        if ($status === UserStatus::Invited) {
            throw ValidationException::withMessages([
                'status' => 'An account becomes invited only through the onboarding flow.',
            ]);
        }

        return DB::transaction(function () use ($actor, $uuids, $status): BulkActionResultData {
            $users = User::query()
                ->with([
                    'roles:id,name',
                    'roles.permissions:id,name',
                    'permissions:id,name',
                ])
                ->whereIn('uuid', $uuids)
                ->lockForUpdate()
                ->get();

            if ($users->count() !== count($uuids)) {
                throw ValidationException::withMessages([
                    'uuids' => 'One or more users no longer exist.',
                ]);
            }

            $updated = 0;
            $skipped = [];

            foreach ($users as $user) {
                $reason = $this->skipReason($actor, $user, $status);

                if ($reason !== null) {
                    $skipped[] = new BulkSkipData((string) $user->uuid, $reason);

                    continue;
                }

                $previous = $user->status;

                $user->forceFill([
                    'status' => $status,
                    'suspended_at' => $status === UserStatus::Suspended ? now() : null,
                ])->save();

                if ($status === UserStatus::Suspended) {
                    // Access is withdrawn now, not on the next login attempt.
                    $user->tokens()->delete();

                    if (config('session.driver') === 'database') {
                        DB::table((string) config('session.table', 'sessions'))
                            ->where('user_id', $user->getKey())
                            ->delete();
                    }
                }

                $this->audit->log('user.status.changed', $user, [
                    'from' => $previous->value,
                    'to' => $status->value,
                ], $actor, 'users');

                $updated++;
            }

            return new BulkActionResultData($updated, $skipped);
        });
    }

    private function skipReason(User $actor, User $user, UserStatus $status): ?string
    {
        if ($status === UserStatus::Suspended && $actor->is($user)) {
            return 'You cannot suspend your own account.';
        }

        if ($status === UserStatus::Suspended && $this->ceiling->isLastActiveSuperAdmin($user)) {
            return 'The last active super administrator cannot be suspended.';
        }

        if ($status === UserStatus::Suspended && $user->isInvited()) {
            return 'An invited account cannot be suspended. Delete it or resend the invitation.';
        }

        if ($user->status === $status) {
            return 'The account already has this status.';
        }

        if ($status === UserStatus::Active && $user->isInvited()) {
            return 'Invited accounts must be activated through their invitation link.';
        }

        $ability = $status === UserStatus::Suspended ? 'suspend' : 'activate';

        if (! Gate::forUser($actor)->allows($ability, $user)) {
            return 'You are not allowed to change this account.';
        }

        return null;
    }
}
