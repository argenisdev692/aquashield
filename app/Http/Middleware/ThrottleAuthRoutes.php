<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

/**
 * Rate limits the auth routes Fortify registers without a named limiter.
 *
 * Fortify owns the routes for password confirmation, password change, profile
 * update and password reset, so we cannot attach `throttle:*` middleware in a
 * route file. This middleware matches the route name after routing and applies
 * the same keys a route middleware would.
 *
 * The limits are deliberately per actor/IP as well as per IP:
 *  - password reset request: 5/min per email+IP, 20/min per IP;
 *  - password confirmation / password change: 5/min per user+IP, 20/min per IP;
 *  - profile update: 10/min per user+IP;
 *  - passkey delete: 10/min per user+IP;
 *  - reset submission: 10/min per IP.
 *
 * It runs inside the `web` group, after the session is available, so
 * `$request->user()` can resolve the authenticated actor.
 */
final class ThrottleAuthRoutes
{
    private const int MINUTE = 60;

    public function handle(Request $request, Closure $next): Response
    {
        $route = $request->route();

        if (! $route instanceof Route) {
            return $next($request);
        }

        $name = $route->getName();

        if (! is_string($name)) {
            return $next($request);
        }

        $ip = (string) $request->ip();
        $userId = $request->user()?->getAuthIdentifier();
        $actor = is_scalar($userId) ? (string) $userId : 'guest';

        if ($name === 'password.email') {
            $email = $request->input('email');
            $email = is_string($email) ? strtolower(trim($email)) : '';

            $this->enforce('password-reset:'.$email.'|'.$ip, 5);
            $this->enforce('password-reset-ip:'.$ip, 20);
        } elseif ($name === 'password.confirm.store' || $name === 'user-password.update') {
            $this->enforce('auth-password-check:'.$actor.'|'.$ip, 5);
            $this->enforce('auth-password-check-ip:'.$ip, 20);
        } elseif ($name === 'user-profile-information.update') {
            $this->enforce('profile-update:'.$actor.'|'.$ip, 10);
        } elseif ($name === 'passkey.destroy') {
            $this->enforce('passkey-delete:'.$actor.'|'.$ip, 10);
        } elseif ($name === 'password.update') {
            $this->enforce('password-reset-submit:'.$ip, 10);
        }

        return $next($request);
    }

    private function enforce(string $key, int $maxAttempts): void
    {
        if (RateLimiter::tooManyAttempts($key, $maxAttempts)) {
            $retryAfter = RateLimiter::availableIn($key);

            throw new ThrottleRequestsException(
                'Too many attempts. Try again in '.$retryAfter.' seconds.',
                null,
                [
                    'Retry-After' => (string) $retryAfter,
                    'X-RateLimit-Limit' => (string) $maxAttempts,
                    'X-RateLimit-Remaining' => '0',
                ],
            );
        }

        RateLimiter::hit($key, self::MINUTE);
    }
}
