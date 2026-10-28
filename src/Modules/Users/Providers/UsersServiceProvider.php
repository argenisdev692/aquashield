<?php

declare(strict_types=1);

namespace Modules\Users\Providers;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
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
    }
}
