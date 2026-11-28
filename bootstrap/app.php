<?php

declare(strict_types=1);

use App\Http\Middleware\EnforceIdleSession;
use App\Http\Middleware\EnsureAccountIsActive;
use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Modules\Backups\Domain\Exceptions\BackupDestinationUnavailable;
use Modules\LeadAppointment\Domain\Exceptions\AppointmentNotFoundException;
use Modules\LeadAppointment\Domain\Exceptions\AvailabilityConflictException;
use Modules\LeadAppointment\Domain\Exceptions\CapacityExceededException;
use Modules\LeadAppointment\Domain\Exceptions\InvalidSchedulingTimezoneException;
use Modules\LeadAppointment\Domain\Exceptions\InvalidStatusTransitionException;
use Modules\LeadAppointment\Domain\Exceptions\LeadHasAppointmentsException;
use Modules\LeadAppointment\Domain\Exceptions\LeadNotFoundException;
use Modules\LeadAppointment\Domain\Exceptions\PastDateSchedulingException;
use Modules\LeadAppointment\Domain\Exceptions\SlotUnavailableException;
use Shared\Infrastructure\Http\Middleware\AssignRequestId;
use Shared\Infrastructure\Http\Middleware\SecurityHeaders;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Railway terminates TLS at its edge proxy and forwards the original
        // scheme in X-Forwarded-Proto. Without trusting that proxy Laravel
        // believes every request is plain HTTP, so @vite/asset() emit
        // `http://...` URLs on an HTTPS page: mixed content blocks the JS and
        // CSP `'self'` no longer matches the assets (black screen). The
        // container is only reachable through the platform edge, so trusting
        // the forwarded headers is safe and survives Railway's dynamic IPs.
        $middleware->trustProxies(at: '*');

        // Correlation id before any other middleware so an early failure still
        // has a request id in the log context; successful responses echo it.
        $middleware->prepend(AssignRequestId::class);

        // SecurityHeaders FIRST: nonce sync (Vite) + CSP/OWASP headers must
        // exist before HandleInertiaRequests renders the root view.
        $middleware->web(prepend: [
            SecurityHeaders::class,
        ]);
        $middleware->api(prepend: [
            SecurityHeaders::class,
        ]);
        $middleware->web(append: [
            // Runs before Inertia renders: an expired session must never
            // produce a page payload for a user who is already signed out.
            EnforceIdleSession::class,
            // Account suspension is immediate: an existing session is
            // terminated before the page is built, not on the next login.
            EnsureAccountIsActive::class,
            HandleInertiaRequests::class,
        ]);
        $middleware->alias([
            'role' => RoleMiddleware::class,
            'permission' => PermissionMiddleware::class,
            'role_or_permission' => RoleOrPermissionMiddleware::class,
            'account.active' => EnsureAccountIsActive::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        $exceptions->render(function (LeadNotFoundException|AppointmentNotFoundException $exception, Request $request) {
            if (! $request->is('api/*') && ! $request->expectsJson()) {
                return null;
            }

            return response()->json(['message' => $exception->getMessage()], 404);
        });

        $exceptions->render(function (SlotUnavailableException|PastDateSchedulingException|CapacityExceededException|InvalidStatusTransitionException|InvalidSchedulingTimezoneException|AvailabilityConflictException|LeadHasAppointmentsException $exception, Request $request) {
            if (! $request->is('api/*') && ! $request->expectsJson()) {
                return null;
            }

            return response()->json(['message' => $exception->getMessage()], 422);
        });

        // An unreachable destination is a 503, not an empty inventory: the
        // panel must not imply the backups are gone when it cannot see them.
        $exceptions->render(function (BackupDestinationUnavailable $exception, Request $request) {
            if ($request->is('api/*') || ($request->expectsJson() && ! $request->header('X-Inertia'))) {
                return response()->json(['message' => $exception->getMessage()], 503);
            }

            abort(503, $exception->getMessage());
        });
    })->create();
