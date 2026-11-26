<?php

declare(strict_types=1);

use Mockery\MockInterface;
use Modules\Backups\Application\Ports\BackupRunLock;
use Modules\Backups\Application\Ports\DatabaseBackupRunner;
use Shared\Domain\Ports\AuditPort;
use Tests\TestCase;

uses(TestCase::class);

it('runs the database dump under the shared lock and audits it', function (): void {
    $this->mock(BackupRunLock::class, function (MockInterface $mock): void {
        $mock->shouldReceive('acquire')->once()->andReturnTrue();
        $mock->shouldReceive('release')->once();
    });

    $this->mock(DatabaseBackupRunner::class, function (MockInterface $mock): void {
        $mock->shouldReceive('run')->once();
    });

    $this->mock(AuditPort::class, function (MockInterface $mock): void {
        $mock->shouldReceive('log')
            ->twice()
            ->withArgs(fn (...$args): bool => in_array($args[0], ['backup.started', 'backup.completed'], true));
    });

    $this->artisan('backups:run')->assertSuccessful();
});

it('skips when another backup holds the lock and audits the skip', function (): void {
    $this->mock(BackupRunLock::class, function (MockInterface $mock): void {
        $mock->shouldReceive('acquire')->once()->andReturnFalse();
        $mock->shouldNotReceive('release');
    });

    $this->mock(DatabaseBackupRunner::class)->shouldNotReceive('run');

    $this->mock(AuditPort::class, function (MockInterface $mock): void {
        $mock->shouldReceive('log')
            ->once()
            ->withArgs(fn (...$args): bool => $args[0] === 'backup.skipped');
    });

    $this->artisan('backups:run')
        ->expectsOutputToContain('already running')
        ->assertSuccessful();
});

it('audits a failed scheduled run with the exception class only', function (): void {
    $this->mock(BackupRunLock::class, function (MockInterface $mock): void {
        $mock->shouldReceive('acquire')->once()->andReturnTrue();
        $mock->shouldReceive('release')->once();
    });

    $this->mock(DatabaseBackupRunner::class, function (MockInterface $mock): void {
        $mock->shouldReceive('run')->once()->andThrow(new RuntimeException('dump failed'));
    });

    $this->mock(AuditPort::class, function (MockInterface $mock): void {
        $mock->shouldReceive('log')
            ->once()
            ->withArgs(fn (...$args): bool => $args[0] === 'backup.started');

        $mock->shouldReceive('log')
            ->once()
            ->withArgs(fn (...$args): bool => $args[0] === 'backup.failed'
                && ($args[2]['reason'] ?? null) === RuntimeException::class);
    });

    expect(fn () => $this->artisan('backups:run'))->toThrow(RuntimeException::class);
});
