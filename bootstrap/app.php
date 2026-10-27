<?php

declare(strict_types=1);

use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
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

        // SecurityHeaders FIRST: nonce sync (Vite) + CSP/OWASP headers must
        // exist before HandleInertiaRequests renders the root view.
        $middleware->web(prepend: [
            SecurityHeaders::class,
        ]);
        $middleware->web(append: [
            HandleInertiaRequests::class,
        ]);
        $middleware->alias([
            'role' => RoleMiddleware::class,
            'permission' => PermissionMiddleware::class,
            'role_or_permission' => RoleOrPermissionMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
