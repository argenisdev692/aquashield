<?php

declare(strict_types=1);

namespace App\Providers;

use App\Actions\Auth\RecordFailedLoginAction;
use App\Actions\Fortify\CreateNewUser;
use App\Actions\Fortify\ResetUserPassword;
use App\Actions\Fortify\UpdateUserPassword;
use App\Actions\Fortify\UpdateUserProfileInformation;
use App\Models\User;
use App\Support\AccountAccess;
use App\Support\AccountLockout;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Actions\RedirectIfTwoFactorAuthenticatable;
use Laravel\Fortify\Features;
use Laravel\Fortify\Fortify;
use Laravel\Passkeys\Contracts\PasskeyUser;
use Laravel\Passkeys\Passkeys;

class FortifyServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Fortify::createUsersUsing(CreateNewUser::class);
        Fortify::updateUserProfileInformationUsing(UpdateUserProfileInformation::class);
        Fortify::updateUserPasswordsUsing(UpdateUserPassword::class);
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);
        Fortify::redirectUserForTwoFactorAuthenticationUsing(RedirectIfTwoFactorAuthenticatable::class);

        /*
         * Password sign-in is the only transport where Fortify owns the
         * credential check, so this is where the verified-email gate belongs.
         * Returning null preserves Fortify's normal `auth.failed` path and its
         * Failed event; an unverified account gets a dedicated 422 on `email`,
         * which the login screen renders as an alert.
         */
        Fortify::authenticateUsing(function (Request $request): ?User {
            $email = Str::lower((string) $request->input('email'));
            $user = User::query()->where('email', $email)->first();

            if (! $user instanceof User) {
                return null;
            }

            /*
             * The durable lock is checked before the password: a locked account
             * rejects even the correct credential for the rest of the hour.
             * This is deliberately separate from the 5/min route limiter —
             * the limiter bounds a burst, this bounds the account.
             */
            if (AccountLockout::active($user)) {
                throw ValidationException::withMessages([
                    'email' => AccountLockout::message($user),
                ]);
            }

            if (! Hash::check((string) $request->input('password'), (string) $user->password)) {
                app(RecordFailedLoginAction::class)->execute($user);

                return null;
            }

            if (! $user->hasVerifiedEmail()) {
                throw ValidationException::withMessages([
                    'email' => __('Your email address is not verified. Check your inbox for the verification link, then sign in again.'),
                ]);
            }

            AccountAccess::assertCanSignIn($user);

            return $user;
        });

        Fortify::loginView(fn () => inertia('Auth/Login', [
            'canResetPassword' => Features::enabled(Features::resetPasswords()),
            'status' => session('status'),
            // Local convenience only. The password lives in .env (never in
            // source); when it is absent the demo block is not rendered.
            'demoCredentials' => app()->isLocal() && config('seed.user_password') ? [
                'email' => 'demo@aquashield.test',
                'password' => config('seed.user_password'),
                'name' => 'AquaShield Demo',
            ] : null,
        ]));
        Fortify::requestPasswordResetLinkView(fn () => inertia('Auth/ForgotPassword', [
            'status' => session('status'),
        ]));
        Fortify::resetPasswordView(fn (Request $request) => inertia('Auth/ResetPassword', [
            'email' => $request->input('email'),
            'token' => $request->route('token'),
        ]));
        Fortify::verifyEmailView(function (Request $request) {
            $user = $request->user();

            return inertia('Auth/VerifyEmail', [
                'status' => session('status'),
                'email' => $user instanceof User ? $user->email : null,
            ]);
        });
        Fortify::confirmPasswordView(fn () => inertia('Auth/ConfirmPassword'));
        Fortify::twoFactorChallengeView(fn () => inertia('Auth/TwoFactorChallenge'));

        RateLimiter::for('login', function (Request $request) {
            $throttleKey = Str::transliterate(Str::lower($request->input(Fortify::username())).'|'.$request->ip());

            return Limit::perMinute(5)->by($throttleKey);
        });

        RateLimiter::for('two-factor', function (Request $request) {
            return Limit::perMinute(5)->by($request->session()->get('login.id'));
        });

        RateLimiter::for('passkeys', function (Request $request) {
            $credentialId = $request->input('credential.id');

            return Limit::perMinute(10)->by(
                ($credentialId ?: $request->session()->getId()).'|'.$request->ip()
            );
        });

        /*
         * `laravel/passkeys` defaults the post-ceremony redirect to "/", which
         * is the marketing entry, not the workspace. The rest of the package
         * config is left at its defaults: relying party and allowed origin are
         * derived from APP_URL, which is the value that has to be right anyway.
         */
        config(['passkeys.redirect' => '/dashboard']);

        /*
         * Passkey sign-in never reaches `authenticateUsing`, so the two gates
         * that protect the password transport are repeated here. Without this,
         * a passkey would be a way around the durable lockout — and around the
         * verified-email gate, since an unverified account can still enrol one.
         */
        Passkeys::authorizeLoginUsing(function (Request $request, PasskeyUser $user): bool {
            if (! $user instanceof User) {
                return false;
            }

            if (AccountLockout::active($user)) {
                throw ValidationException::withMessages([
                    'credential' => AccountLockout::message($user),
                ]);
            }

            if (! $user->hasVerifiedEmail()) {
                throw ValidationException::withMessages([
                    'credential' => __('Your email address is not verified. Check your inbox for the verification link, then sign in again.'),
                ]);
            }

            AccountAccess::assertCanSignIn($user);

            return true;
        });

        /*
         * Email one-time-code login. Sending is capped per email+IP so an
         * attacker cannot turn the endpoint into a mail bomb, and verification
         * is capped per IP so a six-digit code cannot be brute-forced. The
         * package keeps its own per-user attempt budget. The send response is
         * deliberately identical for known and unknown addresses — the
         * throttle is the abuse control, not enumeration.
         */
        RateLimiter::for('otp-send', function (Request $request) {
            $email = Str::lower((string) $request->input('email'));

            return Limit::perMinute(3)->by($email.'|'.$request->ip());
        });

        RateLimiter::for('otp-verify', function (Request $request) {
            // IP, not session id: a session id can rotate between the two
            // steps, and the per-user attempt budget is already enforced by
            // the package. The route throttle is the outer bound.
            return Limit::perMinute(5)->by($request->ip());
        });

        /*
         * Passwordless TOTP entry (email + authenticator code) and the API
         * token surface. The web and API credentials are different transports,
         * so each gets its own bucket; sharing one would let an API client
         * exhaust a user's web login budget.
         */
        RateLimiter::for('authenticator-login', function (Request $request) {
            $email = Str::lower((string) $request->input('email'));

            return Limit::perMinute(5)->by($email.'|'.$request->ip());
        });

        RateLimiter::for('api-login', function (Request $request) {
            $email = Str::lower((string) $request->input('email'));

            return Limit::perMinute(5)->by($email.'|'.$request->ip());
        });

        RateLimiter::for('api', function (Request $request) {
            $user = $request->user();

            return Limit::perMinute(60)->by(
                $user?->getAuthIdentifier() ?? $request->ip()
            );
        });
    }
}
