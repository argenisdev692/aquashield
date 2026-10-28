<?php

declare(strict_types=1);

namespace Modules\ActivityLog\Providers;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

final class ActivityLogServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // Sanctum is the API transport; the Gate abilities are its view of the
        // same Spatie permissions the web routes check. One authorisation
        // source, two transports.
        Gate::define(
            'view-any-activity-logs',
            static fn (User $user): bool => $user->getAllPermissions()
                ->contains('name', 'VIEW_ANY_ACTIVITY_LOGS'),
        );

        Gate::define(
            'view-activity-log',
            static fn (User $user): bool => $user->getAllPermissions()
                ->contains('name', 'VIEW_ACTIVITY_LOGS'),
        );

        $this->loadRoutesFrom(__DIR__.'/../Routes/web.php');
        $this->loadRoutesFrom(__DIR__.'/../Routes/api.php');
    }
}
