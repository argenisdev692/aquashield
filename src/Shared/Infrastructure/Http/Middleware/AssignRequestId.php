<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Correlates every HTTP log line with a request id.
 *
 * A safe inbound `X-Request-Id` is reused (upstream proxy/worker tracing);
 * anything else gets a fresh UUIDv7. The id is echoed on the response so an
 * operator can find the matching log entries without guessing.
 */
final class AssignRequestId
{
    public function handle(Request $request, Closure $next): Response
    {
        $incoming = $request->header('X-Request-Id');

        $requestId = is_string($incoming) && preg_match('/^[A-Za-z0-9._-]{1,64}$/', $incoming) === 1
            ? $incoming
            : (string) Str::uuid7();

        Log::withContext(['request_id' => $requestId]);

        $response = $next($request);
        $response->headers->set('X-Request-Id', $requestId);

        return $response;
    }
}
