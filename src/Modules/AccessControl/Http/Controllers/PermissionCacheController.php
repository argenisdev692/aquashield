<?php

declare(strict_types=1);

namespace Modules\AccessControl\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Modules\AccessControl\Actions\ResetPermissionCacheAction;

final readonly class PermissionCacheController
{
    public function __construct(
        private ResetPermissionCacheAction $resetCache,
    ) {}

    public function __invoke(Request $request): RedirectResponse|Response
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        $this->resetCache->execute($user);

        if ($request->expectsJson() && ! $request->header('X-Inertia')) {
            return response()->noContent();
        }

        return back()->with('status', 'Permission cache flushed.');
    }
}
