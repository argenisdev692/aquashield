<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Records an explicit "I am still here" from the idle-session dialog.
 *
 * The timestamp is the same one EnforceIdleSession reads. Returning 204 keeps
 * the Inertia client out of it: this is a transport call, not a page visit.
 */
final class KeepSessionAliveController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $request->session()->put('auth.last_activity_at', now()->getTimestamp());

        return response()->noContent();
    }
}
