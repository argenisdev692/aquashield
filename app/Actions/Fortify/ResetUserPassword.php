<?php

declare(strict_types=1);

namespace App\Actions\Fortify;

use App\Enums\SecurityEvent;
use App\Jobs\SendSecurityAlert;
use App\Models\User;
use App\Support\AuthRequestContext;
use Illuminate\Support\Facades\DB;
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

        /*
         * A reset is the recovery path for a compromised credential, so every
         * credential issued before it is untrusted: password, API tokens, web
         * sessions, passkeys and TOTP. The account must re-enrol its factors.
         */
        [$revokedPasskeys, $hadTwoFactor] = DB::transaction(function () use ($user, $input): array {
            $user->forceFill([
                'password' => Hash::make($input['password']),
            ])->save();

            $user->tokens()->delete();

            if (config('session.driver') === 'database') {
                DB::table((string) config('session.table', 'sessions'))
                    ->where('user_id', $user->getKey())
                    ->delete();
            }

            $hadTwoFactor = $user->two_factor_secret !== null
                || $user->two_factor_recovery_codes !== null
                || $user->two_factor_confirmed_at !== null;

            $revokedPasskeys = $user->passkeys()->count();

            if ($revokedPasskeys > 0) {
                $user->passkeys()->delete();
            }

            if ($hadTwoFactor) {
                $user->forceFill([
                    'two_factor_secret' => null,
                    'two_factor_recovery_codes' => null,
                    'two_factor_confirmed_at' => null,
                ])->save();
            }

            return [$revokedPasskeys, $hadTwoFactor];
        }, attempts: 3);

        $context = [
            ...AuthRequestContext::properties(),
            'passkeys_revoked' => (string) $revokedPasskeys,
            'two_factor_disabled' => $hadTwoFactor ? 'yes' : 'no',
        ];

        try {
            $this->audit->log('auth.password.changed', $user, $context, $user, 'auth');

            if ($revokedPasskeys > 0 || $hadTwoFactor) {
                $this->audit->log('auth.factors.revoked', $user, $context, $user, 'auth');
            }
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

        if ($revokedPasskeys > 0 || $hadTwoFactor) {
            try {
                SendSecurityAlert::forUser($user, SecurityEvent::FactorsRevoked, $context);
            } catch (Throwable $exception) {
                report($exception);
            }
        }
    }
}
