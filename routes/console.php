<?php

declare(strict_types=1);

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Database backup and audit schedule
|--------------------------------------------------------------------------
|
| The database is the only backup source (`--only-db`). The panel's "run now"
| dispatches RunDatabaseBackupJob, which calls the same command, so cron and
| the button cannot drift.
|
| Times are in config('app.timezone'). `onOneServer()` needs a shared cache
| store (database/redis in production); `withoutOverlapping()` holds a lock so
| a slow dump cannot start twice.
|
*/
Schedule::command('backup:run --only-db')
    ->name('database-backup')
    ->dailyAt('02:30')
    ->withoutOverlapping(120)
    ->onOneServer()
    ->appendOutputTo(storage_path('logs/backup.log'));

Schedule::command('backup:clean')
    ->name('database-backup-clean')
    ->dailyAt('03:30')
    ->withoutOverlapping()
    ->onOneServer();

Schedule::command('backups:purge-quarantine')
    ->name('database-backup-purge-quarantine')
    ->dailyAt('04:00')
    ->withoutOverlapping()
    ->onOneServer();

Schedule::command('backup:monitor')
    ->name('database-backup-monitor')
    ->dailyAt('07:00')
    ->withoutOverlapping()
    ->onOneServer();

// Retention for the audit trail itself. `--force` is required in production.
Schedule::command('activitylog:clean --force')
    ->name('activitylog-clean')
    ->monthlyOn(1, '04:30')
    ->withoutOverlapping()
    ->onOneServer();
