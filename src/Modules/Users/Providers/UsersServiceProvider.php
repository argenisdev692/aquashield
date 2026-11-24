<?php

declare(strict_types=1);

namespace Modules\Users\Providers;

use App\Models\User;
use App\Support\SuperAdminAccess;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Modules\Users\Jobs\SendUserInvitationJob;
use Modules\Users\Policies\UserPolicy;

final class UsersServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/../Routes/web.php');

        // UserPolicy is not in app/Policies, and the module owns the privilege
        // hierarchy the default discovery cannot infer. Explicit binding keeps
        // the policy's location visible.
        Gate::policy(User::class, UserPolicy::class);

        /*
         * Staff management is a Super Admin capability. It is a Gate rather
         * than a permission row so the shell can ask one capability question
         * without receiving a role list (OWASP: the frontend authorizes by
         * capability, never by role). The route group uses `can:manage-staff`.
         */
        Gate::define('manage-staff', static fn (User $user): bool => SuperAdminAccess::allows($user));

        /*
         * Two layers for invitations:
         *  - the bulk HTTP route is bounded per actor, so one request cannot
         *    fan out 500 mails repeatedly;
         *  - the queued job is bounded per recipient and globally, so the
         *    provider is protected even when the queue drains a bulk request.
         *
         * The job limiter receives the job (RateLimited middleware), not a
         * Request, which is why the callback is typed to the job.
         */
        RateLimiter::for(
            'bulk-invitations',
            static fn (Request $request): Limit => Limit::perMinute(2)
                ->by((string) $request->user()->uuid),
        );

        /*
         * Route-specific limits use NAMED limiters. A bare `throttle:5,1`
         * shares the default user/IP key with the group throttle, so every
         * request would consume the invitation budget. Named limiters key on
         * the action as well as the actor.
         */
        RateLimiter::for(
            'user-availability',
            static fn (Request $request): Limit => Limit::perMinute(60)
                ->by('users.availability:'.$request->user()->uuid),
        );

        RateLimiter::for(
            'user-exports',
            static fn (Request $request): Limit => Limit::perMinute(10)
                ->by('users.exports:'.$request->user()->uuid),
        );

        RateLimiter::for(
            'user-invitations',
            static fn (Request $request): Limit => Limit::perMinute(5)
                ->by('users.invitations:'.$request->user()->uuid),
        );

        RateLimiter::for(
            'user-password-resets',
            static fn (Request $request): Limit => Limit::perMinute(5)
                ->by('users.password-resets:'.$request->user()->uuid),
        );

        RateLimiter::for('invitations', static function (SendUserInvitationJob $job): array {
            return [
                Limit::perMinute(60),
                Limit::perMinute(1)->by($job->user->uuid),
            ];
        });
    }
}
