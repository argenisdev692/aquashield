<?php

declare(strict_types=1);

namespace Modules\Users\Actions;

use App\Enums\SecurityEvent;
use App\Jobs\SendSecurityAlert;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Modules\Users\Data\UpdateUserData;
use Modules\Users\Jobs\SendEmailVerificationJob;
use Shared\Domain\Ports\AuditPort;

/**
 * Updates profile fields only. Roles and direct permissions have their own
 * actions so an access change is never hidden inside a profile save.
 */
final readonly class UpdateUserAction
{
    public function __construct(private AuditPort $audit) {}

    public function execute(UpdateUserData $data, User $target, User $actor): User
    {
        $oldEmail = (string) $target->email;

        $updated = DB::transaction(function () use ($data, $target, $actor): User {
            $locked = User::query()
                ->whereKey($target->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $locked->loadMissing('roles:id,name');

            Gate::forUser($actor)->authorize('update', $locked);

            $previousEmail = (string) $locked->email;

            $locked->fill([
                'first_name' => $data->firstName,
                'last_name' => $data->lastName,
                'username' => $data->username,
                'email' => $data->email,
                'phone' => $data->phone,
                'date_of_birth' => $data->dateOfBirth,
                'address' => $data->address,
                'address_2' => $data->address2,
                'zip_code' => $data->zipCode,
                'city' => $data->city,
                'state' => $data->state,
                'country' => $data->country,
                'latitude' => $data->latitude,
                'longitude' => $data->longitude,
            ]);

            /*
             * A new address is not trusted until its owner confirms it. Keeping
             * the old `email_verified_at` would let an administrator silently
             * move an account to an address the owner never proved.
             */
            if ($previousEmail !== $locked->email) {
                $locked->email_verified_at = null;
            }

            $locked->save();

            $changed = array_values(array_diff(
                array_keys($locked->getChanges()),
                ['updated_at'],
            ));

            $this->audit->log('user.updated', $locked, [
                'changed' => $changed,
            ], $actor, 'users');

            return $locked->load(['roles:id,name', 'permissions:id,name']);
        });

        if ($oldEmail !== $updated->email) {
            // The new address must prove ownership; the old address is told
            // out-of-band so a silent account move does not go unnoticed.
            SendEmailVerificationJob::dispatch($updated);
            SendSecurityAlert::dispatch(
                $oldEmail,
                $updated->name,
                SecurityEvent::EmailChanged,
                ['new_email' => (string) $updated->email],
            );
        }

        return $updated;
    }
}
