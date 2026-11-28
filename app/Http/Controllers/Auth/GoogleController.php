<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\AccountAccess;
use App\Support\AccountLockout;
use GuzzleHttp\Client;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\AbstractProvider;
use Laravel\Socialite\Two\User as SocialiteUser;
use Shared\Domain\Ports\AuditPort;
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
 * 4. Google is a sign-in transport for already-registered staff with a
 *    verified local e-mail. Unknown Google identities are refused; there is no
 *    auto-provisioning, and a Google-verified address never promotes an
 *    unverified local row.
 * 5. Google is not an exception to the account lifecycle or the durable
 *    password lockout; both are checked before the session is created.
 */
class GoogleController extends Controller
{
    public function __construct(
        private readonly AuditPort $audit,
    ) {}

    /**
     * Send the visitor to Google's consent screen. Socialite stores the
     * `state` in the session here; the callback refuses anything else.
     */
    public function redirect(): SymfonyRedirectResponse
    {
        $driver = Socialite::driver('google');

        if ($driver instanceof AbstractProvider) {
            $driver->setHttpClient($this->httpClient());
        }

        /** @var SymfonyRedirectResponse $response */
        $response = $driver->redirect();

        return $response;
    }

    /**
     * Handle the return from Google.
     */
    public function callback(Request $request): RedirectResponse
    {
        try {
            $driver = Socialite::driver('google');

            if ($driver instanceof AbstractProvider) {
                $driver->setHttpClient($this->httpClient());
            }

            /** @var SocialiteUser $googleUser */
            $googleUser = $driver->user();
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

        $linked = false;

        // Google is a sign-in transport for already-registered staff. There is
        // no auto-provisioning: an unknown Google identity is refused, and a
        // known address linked to a different Google subject is never re-linked.
        if ($user === null) {
            return $this->signInFailed();
        }

        if ($user->google_id !== null && $user->google_id !== $googleId) {
            return $this->signInFailed();
        }

        // Google is a separate credential, but the account lifecycle and the
        // durable lockout are account-wide. Without these checks, Google is a
        // way around a password lock or a suspension.
        try {
            AccountAccess::assertCanSignIn($user);
        } catch (ValidationException) {
            return $this->signInFailed();
        }

        if (AccountLockout::active($user)) {
            return $this->signInFailed();
        }

        // The local account must already have proved ownership of its address.
        // A Google-verified address does NOT promote an unverified local row:
        // the account owner may have registered with an address they do not
        // control. Verification happens through the app's own email flow.
        if (! $user->hasVerifiedEmail()) {
            return $this->signInFailed();
        }

        if ($user->google_id === null) {
            $user->update(['google_id' => $googleId]);
            $linked = true;
        }

        if ($linked) {
            $this->auditAccountLink($user);
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
     * The OAuth token exchange and userinfo calls must not inherit Guzzle's
     * unbounded default timeout.
     */
    private function httpClient(): Client
    {
        return new Client([
            'timeout' => (float) config('services.google.timeout', 10),
            'connect_timeout' => (float) config('services.google.connect_timeout', 5),
        ]);
    }

    private function auditAccountLink(User $user): void
    {
        try {
            $this->audit->log(
                'user.google.linked',
                $user,
                ['email_domain' => Str::after((string) $user->email, '@')],
                $user,
                'auth',
            );
        } catch (Throwable $exception) {
            // The account is already linked; an audit outage must not turn a
            // successful sign-in into a 500.
            report($exception);
        }
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
