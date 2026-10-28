<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirectResponse;
use Throwable;

/**
 * Google sign-in over Laravel Socialite.
 *
 * The only OAuth provider wired today: a generic multi-provider table for a
 * single provider would be a presumptive feature (Rule of Three).
 *
 * The callback is the security-sensitive half. Three decisions worth keeping
 * explicit:
 *
 * 1. Socialite's `state` (stored by `redirect()`, verified by `user()`) is
 *    the CSRF gate for the callback — a mismatched or missing state throws
 *    before any token exchange happens.
 * 2. An existing email/password account is linked only when Google reports
 *    the address as verified. Unverified or server-to-server responses are
 *    refused, because the link itself is the account-takeover primitive.
 * 3. Accounts with 2FA enabled never bypass the challenge: the same session
 *    hand-off Fortify's own login uses (`login.id`) routes them through
 *    /two-factor-challenge instead of straight into the session.
 */
class GoogleController extends Controller
{
    /**
     * Send the visitor to Google's consent screen. Socialite stores the
     * `state` in the session here; the callback refuses anything else.
     */
    public function redirect(): SymfonyRedirectResponse
    {
        /** @var SymfonyRedirectResponse $response */
        $response = Socialite::driver('google')->redirect();

        return $response;
    }

    /**
     * Handle the return from Google.
     */
    public function callback(Request $request): RedirectResponse
    {
        try {
            /** @var SocialiteUser $googleUser */
            $googleUser = Socialite::driver('google')->user();
        } catch (Throwable $exception) {
            // Invalid state, denied consent, or a failed token exchange all
            // land here. Log it; the visitor gets one neutral message.
            report($exception);

            return $this->signInFailed();
        }

        /** @var string|null $email */
        $email = $googleUser->getEmail();

        if ($email === null || ($googleUser->getRaw()['email_verified'] ?? false) !== true) {
            return $this->signInFailed();
        }

        // Every other auth path lowercases before lookup (Fortify's
        // lowercase_usernames, OTP, API, authenticator). Google is the one
        // provider that can return mixed-case addresses, so normalise here too
        // or the same account becomes two rows on a case-sensitive collation.
        $email = Str::lower($email);

        $googleId = (string) $googleUser->getId();

        $user = User::query()->where('google_id', $googleId)->first()
            ?? User::query()->where('email', $email)->first();

        if ($user === null) {
            [$firstName, $lastName] = $this->splitName(
                (string) $googleUser->getName(),
                $email,
            );

            $user = User::create([
                'first_name' => $firstName,
                'last_name' => $lastName,
                'email' => $email,
                // A random secret: this account signs in via Google. If the
                // owner ever wants a password, the reset flow sets one.
                'password' => Str::password(32),
                'google_id' => $googleId,
            ]);
        } elseif ($user->google_id === null) {
            $user->update(['google_id' => $googleId]);
        } elseif ($user->google_id !== $googleId) {
            // The address resolves to a different Google identity — never
            // re-link silently; that is exactly the takeover case.
            return $this->signInFailed();
        }

        // A Google-verified address is authoritative, including for an
        // existing local account that had not confirmed its email yet.
        if (! $user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
        }

        if ($user->two_factor_secret !== null
            && in_array(TwoFactorAuthenticatable::class, class_uses_recursive($user), true)) {
            $request->session()->put([
                'login.id' => $user->getKey(),
                'login.remember' => false,
            ]);

            return redirect()->route('two-factor.login');
        }

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->intended('/dashboard');
    }

    /**
     * Split the single Google display name into the canonical first/last pair.
     *
     * @return array{0: string, 1: string|null}
     */
    private function splitName(string $fullName, string $email): array
    {
        $parts = preg_split('/\s+/', trim($fullName)) ?: [];
        $firstName = array_shift($parts) ?: Str::before($email, '@');

        return [
            $firstName !== '' ? $firstName : 'User',
            $parts === [] ? null : implode(' ', $parts),
        ];
    }

    /**
     * One neutral message for every failure mode — the visitor retries or
     * falls back to the password form; nothing internal is echoed back.
     */
    private function signInFailed(): RedirectResponse
    {
        return redirect()
            ->route('login')
            ->with('status', 'Google sign-in could not be completed. Please try again or sign in with your email and password.');
    }
}
