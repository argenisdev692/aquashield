<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use App\Support\AccountAccess;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Enforces the staff lifecycle on every authenticated request.
 *
 * `AccountAccess` already rejects a suspended or invited account at the five
 * sign-in transports. This middleware is the second half: it terminates an
 * existing session immediately after an administrator suspends the account,
 * and it keeps a manually onboarded user on the password tab until the temporary
 * credential has been replaced.
 */
final class EnsureAccountIsActive
{
    /**
     * Routes a forced password change must still reach. The settings page hosts
     * the form; the update endpoint is Fortify's; logout and the idle heartbeat
     * must not trap the browser.
     *
     * @var list<string>
     */
    private const array PASSWORD_CHANGE_EXEMPT = [
        'settings.profile.*',
        'user-password.update',
        'logout',
        'auth.keep-alive',
        'password.*',
        'verification.*',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return $next($request);
        }

        if (! $user->status->canSignIn()) {
            if ($request->hasSession()) {
                Auth::guard('web')->logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();
            }

            $message = AccountAccess::messageFor($user)
                ?? 'This account cannot sign in.';

            if ($request->header('X-Inertia') !== null) {
                return response()->noContent(409)->header('X-Inertia-Location', route('login'));
            }

            if ($request->expectsJson()) {
                return response()->json(['message' => $message], 403);
            }

            return to_route('login')->withErrors(['email' => $message]);
        }

        if ($user->requiresPasswordChange() && ! $request->routeIs(...self::PASSWORD_CHANGE_EXEMPT)) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Change your temporary password before continuing.',
                ], 403);
            }

            return redirect()
                ->to(route('settings.profile.edit').'#password')
                ->with('status', 'Change your temporary password before continuing.');
        }

        return $next($request);
    }
}
