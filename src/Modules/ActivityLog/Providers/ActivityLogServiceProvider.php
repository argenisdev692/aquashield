<?php

declare(strict_types=1);

namespace Modules\ActivityLog\Providers;

use Illuminate\Support\ServiceProvider;
use RuntimeException;

final class ActivityLogServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        self::assertValidConfiguration();
    }

    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/../Routes/web.php');
    }

    /**
     * Two configuration values can destroy audit data or break every audit
     * write. Both fail closed at boot instead of at 04:30 on the first of the
     * month.
     */
    public static function assertValidConfiguration(): void
    {
        $days = config('activitylog.clean_after_days');

        if (! is_int($days) || $days < 365) {
            throw new RuntimeException(
                'activitylog.clean_after_days must be an integer >= 365; got '.var_export($days, true).'. '
                .'A zero value makes activitylog:clean delete the entire audit trail.',
            );
        }

        if (filter_var(config('activitylog.buffer.enabled', false), FILTER_VALIDATE_BOOLEAN)) {
            throw new RuntimeException(
                'activitylog.buffer.enabled must stay false while the activity_log.uuid column is NOT NULL '
                .'and has no database default: buffered inserts bypass HasUuids and would fail every audit write.',
            );
        }
    }
}
