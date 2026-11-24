<?php

declare(strict_types=1);

namespace Modules\Users\Actions;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Modules\Users\Data\BulkActionResultData;
use Modules\Users\Data\BulkSkipData;
use Modules\Users\Jobs\SendUserInvitationJob;
use Shared\Domain\Ports\AuditPort;

/**
 * Bulk rotate-and-resend invitations.
 *
 * Only `invited` rows are eligible. The timestamp is rewritten inside the
 * transaction, which invalidates older links; the jobs are dispatched after the
 * commit so a rollback cannot mail a link to a row that no longer exists.
 */
final readonly class ResendInvitationsAction
{
    public function __construct(private AuditPort $audit) {}

    /**
     * @param list<string> $uuids
     */
    public function execute(User $actor, array $uuids): BulkActionResultData
    {
        [$result, $invited] = DB::transaction(function () use ($actor, $uuids): array {
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
            $invited = [];

            foreach ($users as $user) {
                if (! $user->isInvited()) {
                    $skipped[] = new BulkSkipData((string) $user->uuid, 'Only invited accounts can be re-invited.');

                    continue;
                }

                if (! Gate::forUser($actor)->allows('resendInvitation', $user)) {
                    $skipped[] = new BulkSkipData((string) $user->uuid, 'You are not allowed to invite this account.');

                    continue;
                }

                $user->forceFill(['invitation_sent_at' => now()])->save();

                $this->audit->log('user.invitation_resent', $user, [
                    'bulk' => true,
                ], $actor, 'users');

                $invited[] = $user;
                $updated++;
            }

            return [new BulkActionResultData($updated, $skipped), $invited];
        });

        foreach ($invited as $user) {
            SendUserInvitationJob::dispatch(
                $user,
                (int) $user->invitation_sent_at?->getTimestamp(),
            )->onQueue('emails');
        }

        return $result;
    }
}
