<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * The "add a passkey" prompt is dismissed at most once per account.
 *
 * The write is idempotent (a second dismissal just moves the timestamp) and
 * deliberately not reversible from the UI: a user who dismissed the prompt can
 * still add a passkey from Settings → Security.
 */
final class PasskeyPromptController extends Controller
{
    public function store(Request $request): JsonResponse|RedirectResponse
    {
        $user = $request->user();

        abort_unless($user instanceof User, 403);

        $user->forceFill(['passkey_prompt_dismissed_at' => now()])->save();

        if ($request->expectsJson()) {
            return response()->json(['dismissed' => true]);
        }

        return back();
    }
}
