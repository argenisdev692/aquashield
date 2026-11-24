<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\AuthenticatorLoginRequest;
use App\Models\User;
use App\Support\AccountAccess;
use App\Support\AccountLockout;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;
use Laravel\Fortify\Fortify;

/**
 * Passwordless TOTP sign-in: email + the six-digit code from the authenticator
 * app the user already enrolled in Profile & security.
 *
 * This is deliberately NOT a way to enrol 2FA from the login screen: a QR code
 * shown to an unauthenticated visitor would hand out a secret before identity
 * is proven. Enrolment stays behind `auth` + password confirmation; this route
 * only consumes an existing, confirmed secret.
 */
final class AuthenticatorLoginController extends Controller
{
    public function __construct(
        private readonly TwoFactorAuthenticationProvider $twoFactor,
    ) {}

    public function store(AuthenticatorLoginRequest $request): RedirectResponse
    {
        $email = Str::lower($request->string('email')->toString());
        $user = User::query()->where('email', $email)->first();

        /*
         * Parity with the password and passkey transports: a durable lockout
         * must survive every way in. Without this, email + TOTP was a way
         * around the lock that repeated failed passwords had just applied.
         */
        if ($user instanceof User && AccountLockout::active($user)) {
            throw ValidationException::withMessages([
                'email' => AccountLockout::message($user),
            ]);
        }

        $valid = $user instanceof User
            && $user->hasEnabledTwoFactorAuthentication()
            && $this->twoFactor->verify(
                Fortify::currentEncrypter()->decrypt((string) $user->two_factor_secret),
                $request->string('code')->toString(),
            );

        if (! $valid) {
            // One neutral message for unknown users, disabled TOTP and a wrong
            // code: do not turn the endpoint into an account oracle.
            throw ValidationException::withMessages([
                'code' => __('The provided authenticator code was invalid.'),
            ]);
        }

        if (! $user->hasVerifiedEmail()) {
            throw ValidationException::withMessages([
                'email' => __('Your email address is not verified. Check your inbox for the verification link, then sign in again.'),
            ]);
        }

        AccountAccess::assertCanSignIn($user);

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->intended('/dashboard');
    }
}
