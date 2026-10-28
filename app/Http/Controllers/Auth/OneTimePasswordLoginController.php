<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\SendOneTimePasswordRequest;
use App\Http\Requests\Auth\VerifyOneTimePasswordRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Response;
use Throwable;

/**
 * Passwordless email login with a six-digit, single-use code.
 *
 * This is the transport for `spatie/laravel-one-time-passwords`; the package
 * owns creation, expiry, origin binding, rate limiting and consumption. The
 * controller only decides what the visitor sees:
 *
 * 1. Send is enumeration-safe: known and unknown addresses get the same
 *    redirect and status, and the response never says whether mail was sent.
 * 2. The address is held in the server-side session between the two steps, so
 *    the code cannot be replayed against a different account.
 * 3. A successful code does not bypass TOTP. The same `login.id` hand-off
 *    Fortify uses sends a 2FA-enabled account to /two-factor-challenge.
 */
final class OneTimePasswordLoginController extends Controller
{
    public function create(Request $request): Response
    {
        $email = $request->session()->get('otp.email');
        $status = $request->session()->get('status');

        return inertia('Auth/OtpLogin', [
            'email' => is_string($email) ? $email : null,
            'status' => is_string($status) ? $status : null,
        ]);
    }

    public function send(SendOneTimePasswordRequest $request): JsonResponse|RedirectResponse
    {
        $email = Str::lower((string) $request->validated('email'));
        $user = User::query()->where('email', $email)->first();

        try {
            $user?->sendOneTimePassword();
        } catch (Throwable $exception) {
            // A mail outage must not become an account-existence oracle, and it
            // must not turn a public form into a 500. Report it for operators;
            // the visitor still gets the neutral message.
            report($exception);
        }

        $request->session()->put('otp.email', $email);

        $message = 'If that email is registered, a six-digit sign-in code is on its way. It expires in two minutes.';

        if ($request->expectsJson()) {
            return response()->json([
                'message' => $message,
                'email' => $email,
            ], 202);
        }

        return to_route('otp.login')->with('status', $message);
    }

    public function verify(VerifyOneTimePasswordRequest $request): JsonResponse|RedirectResponse
    {
        $email = $request->session()->get('otp.email');

        if (! is_string($email) || $email === '') {
            if ($request->expectsJson()) {
                throw ValidationException::withMessages([
                    'code' => 'Request a new sign-in code first.',
                ]);
            }

            return to_route('otp.login')
                ->withErrors(['code' => 'Request a new sign-in code first.']);
        }

        $user = User::query()->where('email', $email)->first();

        if ($user === null) {
            return $this->verificationFailed($request, 'That code is invalid or has expired.');
        }

        $result = $user->consumeOneTimePassword((string) $request->validated('code'));

        if (! $result->isOk()) {
            return $this->verificationFailed($request, $result->validationMessage());
        }

        // Consuming a code delivered to the address proves ownership. This is
        // the one login transport that can promote an unverified account
        // without a separate round-trip.
        if (! $user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
        }

        $request->session()->forget('otp.email');

        if ($user->hasEnabledTwoFactorAuthentication()) {
            $request->session()->put([
                'login.id' => $user->getKey(),
                'login.remember' => false,
            ]);

            if ($request->expectsJson()) {
                return response()->json([
                    'two_factor' => true,
                    'redirect' => route('two-factor.login'),
                ]);
            }

            return to_route('two-factor.login');
        }

        Auth::login($user);
        $request->session()->regenerate();

        if ($request->expectsJson()) {
            return response()->json([
                'two_factor' => false,
                'redirect' => '/dashboard',
            ]);
        }

        return redirect()->intended('/dashboard');
    }

    private function verificationFailed(
        VerifyOneTimePasswordRequest $request,
        string $message,
    ): RedirectResponse {
        if ($request->expectsJson()) {
            throw ValidationException::withMessages(['code' => $message]);
        }

        return back()->withErrors(['code' => $message]);
    }
}
