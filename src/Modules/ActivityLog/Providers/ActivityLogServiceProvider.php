<?php

declare(strict_types=1);

namespace Modules\ActivityLog\Providers;

use Illuminate\Support\ServiceProvider;

final class ActivityLogServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/../Routes/web.php');
    }
}
