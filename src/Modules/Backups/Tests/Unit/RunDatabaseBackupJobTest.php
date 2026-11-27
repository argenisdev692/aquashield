<?php

declare(strict_types=1);

use Mockery\MockInterface;
use Modules\Backups\Application\Ports\BackupRunLock;
use Modules\Backups\Application\Ports\BackupRunStatusStore;
use Modules\Backups\Application\Ports\DatabaseBackupRunner;
use Modules\Backups\Domain\Enums\BackupRunStatus;
use Modules\Backups\Domain\Exceptions\BackupRunFailed;
use Modules\Backups\Jobs\RunDatabaseBackupJob;
use Shared\Domain\Ports\AuditPort;
use Tests\TestCase;

uses(TestCase::class);

it('runs a database backup and records start and completion', function (): void {
    $this->mock(BackupRunLock::class, function (MockInterface $mock): void {
        $mock->shouldReceive('acquire')->once()->andReturnTrue();
        $mock->shouldReceive('release')->once();
    });

    $this->mock(BackupRunStatusStore::class, function (MockInterface $mock): void {
        $mock->shouldReceive('update')->twice()->withArgs(
            fn (BackupRunStatus $status): bool => in_array(
                $status,
                [BackupRunStatus::Running, BackupRunStatus::Succeeded],
                true,
            ),
        );
    });

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
        app(BackupRunLock::class),
        app(BackupRunStatusStore::class),
    );
});

it('records a failed run without copying the exception message', function (): void {
    $this->mock(BackupRunLock::class, function (MockInterface $mock): void {
        $mock->shouldReceive('acquire')->once()->andReturnTrue();
        $mock->shouldReceive('release')->once();
    });

    $this->mock(BackupRunStatusStore::class, function (MockInterface $mock): void {
        $mock->shouldReceive('update')->times(3)->withArgs(
            fn (BackupRunStatus $status): bool => in_array(
                $status,
                [BackupRunStatus::Running, BackupRunStatus::Failed],
                true,
            ),
        );
    });

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

    expect(fn () => $job->handle(
        app(DatabaseBackupRunner::class),
        app(AuditPort::class),
        app(BackupRunLock::class),
        app(BackupRunStatusStore::class),
    ))->toThrow(BackupRunFailed::class);

    $job->failed(BackupRunFailed::exitCode(1));

    expect($calls)->toHaveCount(2)
        ->and($calls[0][0])->toBe('backup.started')
        ->and($calls[1][0])->toBe('backup.failed')
        ->and($calls[1][2]['reason'])->toBe(BackupRunFailed::class)
        ->and(json_encode($calls[1][2]))->not->toContain('exited with code');
});

it('skips the run when another process holds the shared lock', function (): void {
    $this->mock(BackupRunLock::class, function (MockInterface $mock): void {
        $mock->shouldReceive('acquire')->once()->andReturnFalse();
        $mock->shouldNotReceive('release');
    });

    $this->mock(DatabaseBackupRunner::class)->shouldNotReceive('run');

    $this->mock(BackupRunStatusStore::class, function (MockInterface $mock): void {
        $mock->shouldReceive('update')->once()->with(BackupRunStatus::Skipped);
    });

    $this->mock(AuditPort::class, function (MockInterface $mock): void {
        $mock->shouldReceive('log')
            ->once()
            ->withArgs(fn (...$args): bool => $args[0] === 'backup.skipped');
    });

    (new RunDatabaseBackupJob)->handle(
        app(DatabaseBackupRunner::class),
        app(AuditPort::class),
        app(BackupRunLock::class),
        app(BackupRunStatusStore::class),
    );
});
