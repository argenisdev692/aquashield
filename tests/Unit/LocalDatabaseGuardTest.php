<?php

declare(strict_types=1);

use App\Listeners\GuardDestructiveCommandsOnRemoteDatabase;
use Illuminate\Console\Events\CommandStarting;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;
use Tests\TestCase;

uses(TestCase::class);

function commandStarting(string $command): CommandStarting
{
    return new CommandStarting($command, new ArrayInput([]), new NullOutput);
}

beforeEach(function (): void {
    $this->app['env'] = 'local';
    config([
        'database.default' => 'pgsql',
        'database.connections.pgsql.host' => 'db.example.com',
        'database.allow_remote_destructive' => false,
    ]);
});

it('blocks a destructive command against a remote database in local', function (string $command): void {
    expect(fn () => (new GuardDestructiveCommandsOnRemoteDatabase)->handle(commandStarting($command)))
        ->toThrow(RuntimeException::class);
})->with(['migrate:fresh', 'migrate:refresh', 'migrate:reset', 'migrate:rollback', 'db:wipe', 'db:seed']);

it('allows a non-destructive command against a remote database', function (): void {
    (new GuardDestructiveCommandsOnRemoteDatabase)->handle(commandStarting('migrate'));

    expect(true)->toBeTrue();
});

it('allows a destructive command against a local host', function (): void {
    config(['database.connections.pgsql.host' => '127.0.0.1']);

    (new GuardDestructiveCommandsOnRemoteDatabase)->handle(commandStarting('migrate:fresh'));

    expect(true)->toBeTrue();
});

it('allows the explicit override', function (): void {
    config(['database.allow_remote_destructive' => true]);

    (new GuardDestructiveCommandsOnRemoteDatabase)->handle(commandStarting('db:wipe'));

    expect(true)->toBeTrue();
});

it('does not guard non-local environments', function (): void {
    $this->app['env'] = 'production';

    (new GuardDestructiveCommandsOnRemoteDatabase)->handle(commandStarting('migrate:fresh'));

    expect(true)->toBeTrue();
});
