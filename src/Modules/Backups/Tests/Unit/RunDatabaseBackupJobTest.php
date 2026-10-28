<?php

declare(strict_types=1);

use Mockery\MockInterface;
use Modules\Backups\Application\Repositories\DatabaseBackupRunner;
use Modules\Backups\Domain\Exceptions\BackupRunFailed;
use Modules\Backups\Jobs\RunDatabaseBackupJob;
use Shared\Domain\Ports\AuditPort;
use Tests\TestCase;

uses(TestCase::class);

it('runs a database backup and records start and completion', function (): void {
    $this->mock(DatabaseBackupRunner::class, function (MockInterface $mock): void {
        $mock->shouldReceive('run')->once();
    });

    $this->mock(AuditPort::class, function (MockInterface $mock): void {
        $mock->shouldReceive('log')
            ->twice()
            ->withArgs(fn (...$args): bool => in_array($args[0], ['backup.started', 'backup.completed'], true));
    });

    (new RunDatabaseBackupJob)->handle(
        app(DatabaseBackupRunner::class),
        app(AuditPort::class),
    );
});

it('records a failed run without copying the exception message', function (): void {
    $this->mock(DatabaseBackupRunner::class, function (MockInterface $mock): void {
        $mock->shouldReceive('run')->once()->andThrow(BackupRunFailed::exitCode(1));
    });

    $calls = [];

    $this->mock(AuditPort::class, function (MockInterface $mock) use (&$calls): void {
        $mock->shouldReceive('log')
            ->twice()
            ->andReturnUsing(function (...$args) use (&$calls): void {
                $calls[] = $args;
            });
    });

    $job = new RunDatabaseBackupJob;

    expect(fn () => $job->handle(app(DatabaseBackupRunner::class), app(AuditPort::class)))
        ->toThrow(BackupRunFailed::class);

    $job->failed(BackupRunFailed::exitCode(1));

    expect($calls)->toHaveCount(2)
        ->and($calls[0][0])->toBe('backup.started')
        ->and($calls[1][0])->toBe('backup.failed')
        ->and($calls[1][2]['reason'])->toBe(BackupRunFailed::class)
        ->and(json_encode($calls[1][2]))->not->toContain('exited with code');
});
