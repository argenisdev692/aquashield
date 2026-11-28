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
| The database is the only backup source (`--only-db`). `backups:run` is the
| locked wrapper used by the cron; the panel's "run now" job acquires the same
| `BackupRunLock`. Without that shared lock the cron and the button can both
| enter spatie's single `backup-temp/temp` directory, which the package
| deletes on start.
|
| The backup window is America/Chicago on purpose: the app timezone is UTC,
| and 02:30 UTC would be the previous evening in the business zone.
| `onOneServer()` needs a shared cache store; `withoutOverlapping()` holds a
| lock so a slow dump cannot start twice.
|
*/
Schedule::command('backups:run')
    ->name('database-backup')
    ->timezone('America/Chicago')
    ->dailyAt('02:30')
    ->withoutOverlapping(120)
    ->onOneServer()
    ->appendOutputTo(storage_path('logs/backup.log'));

Schedule::command('backup:clean')
    ->name('database-backup-clean')
    ->timezone('America/Chicago')
    ->dailyAt('03:30')
    ->withoutOverlapping()
    ->onOneServer()
    ->appendOutputTo(storage_path('logs/backup-clean.log'));

Schedule::command('backups:purge-quarantine --force')
    ->name('database-backup-purge-quarantine')
    ->timezone('America/Chicago')
    ->dailyAt('04:00')
    ->withoutOverlapping()
    ->onOneServer()
    ->appendOutputTo(storage_path('logs/backup-quarantine.log'))
    ->emailOutputOnFailure((string) config('backup.notifications.notifications.mail.to'));

// The health check is only useful if a non-zero exit leaves evidence.
Schedule::command('backup:monitor')
    ->name('database-backup-monitor')
    ->timezone('America/Chicago')
    ->dailyAt('07:00')
    ->withoutOverlapping()
    ->onOneServer()
    ->appendOutputTo(storage_path('logs/backup-monitor.log'));

// Retention for the audit trail itself. `--force` is required in production.
Schedule::command('activitylog:clean --force')
    ->name('activitylog-clean')
    ->monthlyOn(1, '04:30')
    ->withoutOverlapping()
    ->onOneServer()
    ->appendOutputTo(storage_path('logs/activitylog-clean.log'))
    ->emailOutputOnFailure(config('activitylog.alert_email') ?: config('mail.from.address'));

/*
| LeadAppointment — US federal holidays are materialised as company availability
| exceptions. Run just after midnight on Jan 1 so New Year's Day is included;
| manual overrides are never purged.
*/
Schedule::command('lead-appointment:sync-holidays')
    ->name('lead-appointment-holidays')
    ->yearlyOn(1, 1, '00:10')
    ->timezone('America/Chicago')
    ->withoutOverlapping(60)
    ->onOneServer()
    ->appendOutputTo(storage_path('logs/lead-appointment-holidays.log'));

// Meta ingestion payloads contain PII; processed rows are not a permanent
// archive. Keep a short forensic window and prune the rest.
Schedule::command('lead-appointment:prune-ingestion-events --days=30')
    ->name('lead-appointment-prune-ingestion')
    ->timezone('America/Chicago')
    ->dailyAt('03:15')
    ->withoutOverlapping()
    ->onOneServer()
    ->appendOutputTo(storage_path('logs/lead-appointment-prune-ingestion.log'));

// Failed jobs can contain serialized mailables with lead PII. Prune them
// weekly; queue:monitor emits QueueBusy, which AppServiceProvider logs.
Schedule::command('queue:prune-failed --hours=168')
    ->name('queue-prune-failed')
    ->weeklyOn(1, '05:00')
    ->withoutOverlapping()
    ->onOneServer();

Schedule::command('queue:monitor redis:default --max=1000')
    ->name('queue-monitor')
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->onOneServer();
