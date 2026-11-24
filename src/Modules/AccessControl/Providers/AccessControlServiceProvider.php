<?php

declare(strict_types=1);

namespace Modules\AccessControl\Providers;

use App\Models\User;
use App\Support\SuperAdminAccess;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

final class AccessControlServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/../Routes/web.php');

        /*
         * The catalogue is installation-level. A capability Gate (not a role
         * list in the frontend) keeps the shell honest: the sidebar asks one
         * boolean question, and every route re-checks the same answer.
         */
        Gate::define('manage-access', static fn (User $user): bool => SuperAdminAccess::allows($user));

        // Named limiter: a bare throttle:10,1 would share the user/IP bucket
        // with the group throttle and quietly consume it.
        RateLimiter::for(
            'access-control-exports',
            static fn (Request $request): Limit => Limit::perMinute(10)
                ->by('access-control.exports:'.$request->user()->uuid),
        );
    }
}
