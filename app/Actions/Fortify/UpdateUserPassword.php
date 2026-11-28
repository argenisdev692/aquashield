<?php

declare(strict_types=1);

namespace App\Actions\Fortify;

use App\Enums\SecurityEvent;
use App\Jobs\SendSecurityAlert;
use App\Models\User;
use App\Support\AuthRequestContext;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\UpdatesUserPasswords;
use Shared\Domain\Ports\AuditPort;
use Throwable;

class UpdateUserPassword implements UpdatesUserPasswords
{
    use PasswordValidationRules;

    public function __construct(
        private readonly AuditPort $audit,
    ) {}

    /**
     * Validate and update the user's password.
     *
     * @param array<string, string> $input
     *
     * @throws ValidationException
     */
    public function update(User $user, array $input): void
    {
        Validator::make($input, [
            'current_password' => ['required', 'string', 'current_password:web'],
            'password' => $this->passwordRules(),
        ], [
            'current_password.current_password' => __('The provided password does not match your current password.'),
        ])->validateWithBag('updatePassword');

        $user->forceFill([
            'password' => Hash::make($input['password']),
            'must_change_password' => false,
        ])->save();

        /*
         * A password change is a credential rotation: every API token and
         * every other web session must stop working. `logoutOtherDevices`
         * rotates the remember token and marks the current session with the
         * new password hash so the user who made the change stays signed in;
         * `AuthenticateSession` then evicts every session still carrying the
         * old hash.
         */
        $user->tokens()->delete();
        Auth::logoutOtherDevices($input['password']);

        $context = AuthRequestContext::properties();

        try {
            $this->audit->log('auth.password.changed', $user, $context, $user, 'auth');
        } catch (Throwable $exception) {
            report($exception);
        }

        try {
            SendSecurityAlert::forUser($user, SecurityEvent::PasswordChanged, $context);
        } catch (Throwable $exception) {
            // The password is already changed; a queue outage must not turn a
            // successful credential update into a 500.
            report($exception);
        }
    }
}
