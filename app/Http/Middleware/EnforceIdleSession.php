<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Server-side half of the idle-session control.
 *
 * The Vue dialog is UX: it warns at `warn_after_minutes` and signs out after
 * the grace period. This middleware is the trust boundary. It runs on every
 * authenticated web request, compares the session's `auth.last_activity_at`
 * with the same policy, and invalidates a session that ignored the warning.
 *
 * `server_buffer_seconds` absorbs clock/network skew: the browser may send the
 * "keep session" request a moment after its own 60-second countdown ends.
 */
final class EnforceIdleSession
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user() === null) {
            return $next($request);
        }

        $session = $request->session();
        $now = now()->getTimestamp();
        $lastActivity = (int) $session->get('auth.last_activity_at', 0);

        $warnSeconds = max(1, (int) config('auth.hardening.idle.warn_after_minutes', 5)) * 60;
        $graceSeconds = max(10, (int) config('auth.hardening.idle.grace_seconds', 60));
        $bufferSeconds = max(0, (int) config('auth.hardening.idle.server_buffer_seconds', 15));
        $expiresAfter = $warnSeconds + $graceSeconds + $bufferSeconds;

        if ($lastActivity > 0 && ($now - $lastActivity) > $expiresAfter) {
            Auth::guard('web')->logout();

            $session->invalidate();
            $session->regenerateToken();

            if ($request->header('X-Inertia') !== null) {
                return response()->noContent(409)->header('X-Inertia-Location', route('login'));
            }

            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Your session expired because it was idle. Sign in again to continue.',
                ], 401);
            }

            return redirect()
                ->route('login')
                ->with('status', 'Your session expired because it was idle. Sign in again to continue.');
        }

        // Any authenticated request is activity: an Inertia visit, a form
        // submit, or the explicit keep-alive ping the dialog sends.
        $session->put('auth.last_activity_at', $now);

        return $next($request);
    }
}
