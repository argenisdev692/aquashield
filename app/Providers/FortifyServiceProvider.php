<?php

declare(strict_types=1);

namespace App\Providers;

use App\Actions\Fortify\CreateNewUser;
use App\Actions\Fortify\ResetUserPassword;
use App\Actions\Fortify\UpdateUserPassword;
use App\Actions\Fortify\UpdateUserProfileInformation;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Laravel\Fortify\Actions\RedirectIfTwoFactorAuthenticatable;
use Laravel\Fortify\Features;
use Laravel\Fortify\Fortify;

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

        Fortify::loginView(fn () => inertia('Auth/Login', [
            'canResetPassword' => Features::resetPasswords(),
            'status' => session('status'),
            'demoCredentials' => app()->isLocal() ? [
                'email' => 'demo@aquashield.test',
                'password' => 'demo01=',
                'name' => 'AquaShield Demo',
            ] : null,
        ]));
        Fortify::registerView(fn () => inertia('Auth/Register'));
        Fortify::requestPasswordResetLinkView(fn () => inertia('Auth/ForgotPassword', [
            'status' => session('status'),
        ]));
        Fortify::resetPasswordView(fn (Request $request) => inertia('Auth/ResetPassword', [
            'email' => $request->input('email'),
            'token' => $request->route('token'),
        ]));
        Fortify::verifyEmailView(fn () => inertia('Auth/VerifyEmail', [
            'status' => session('status'),
        ]));
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
