<?php

declare(strict_types=1);

namespace App\Listeners;

use Illuminate\Console\Events\CommandStarting;
use RuntimeException;

/**
 * Refuses destructive schema/data commands when the local app points at a
 * non-local database.
 *
 * A developer shell with a hosted DSN in .env can wipe that database with
 * `migrate:fresh` or `db:wipe`; neither asks for confirmation outside the
 * production environment. The guard is deliberately local-only (staging and
 * production run their own deploys) and has an explicit escape hatch:
 * `DB_ALLOW_REMOTE_DESTRUCTIVE=true`.
 */
final class GuardDestructiveCommandsOnRemoteDatabase
{
    /** @var list<string> */
    private const array COMMANDS = [
        'db:seed',
        'db:wipe',
        'migrate:fresh',
        'migrate:refresh',
        'migrate:reset',
        'migrate:rollback',
    ];

    public function handle(CommandStarting $event): void
    {
        if (! app()->isLocal() || ! in_array($event->command, self::COMMANDS, true)) {
            return;
        }

        if ((bool) config('database.allow_remote_destructive', false)) {
            return;
        }

        $connection = (string) config('database.default');
        $host = config("database.connections.{$connection}.host");

        if (! is_string($host) || $host === '' || in_array($host, ['127.0.0.1', 'localhost', '::1'], true)) {
            return;
        }

        throw new RuntimeException(
            "Refusing to run [{$event->command}] locally against a non-local database. "
            .'Point DB_HOST at a local database, or set DB_ALLOW_REMOTE_DESTRUCTIVE=true if this is intentional.',
        );
    }
}
