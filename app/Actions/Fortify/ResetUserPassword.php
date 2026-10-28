<?php

declare(strict_types=1);

namespace App\Actions\Fortify;

use App\Enums\SecurityEvent;
use App\Jobs\SendSecurityAlert;
use App\Models\User;
use App\Support\AuthRequestContext;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\ResetsUserPasswords;
use Shared\Domain\Ports\AuditPort;
use Throwable;

class ResetUserPassword implements ResetsUserPasswords
{
    use PasswordValidationRules;

    public function __construct(
        private readonly AuditPort $audit,
    ) {}

    /**
     * Validate and reset the user's forgotten password.
     *
     * @param array<string, string> $input
     *
     * @throws ValidationException
     */
    public function reset(User $user, array $input): void
    {
        Validator::make($input, [
            'password' => $this->passwordRules(),
        ])->validate();

        $user->forceFill([
            'password' => Hash::make($input['password']),
        ])->save();

        $context = AuthRequestContext::properties();

        try {
            $this->audit->log('auth.password.changed', $user, $context, $user, 'auth');
        } catch (Throwable $exception) {
            report($exception);
        }

        try {
            SendSecurityAlert::forUser($user, SecurityEvent::PasswordChanged, $context);
        } catch (Throwable $exception) {
            // The password is already reset; a queue outage must not turn a
            // successful reset into a 500.
            report($exception);
        }
    }
}
