<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Queue;
use Mockery\MockInterface;
use Modules\Backups\Application\Actions\RunDatabaseBackupAction;
use Modules\Backups\Application\Ports\BackupRunStatusStore;
use Modules\Backups\Data\BackupRunStatusData;
use Modules\Backups\Domain\Enums\BackupRunStatus;
use Modules\Backups\Jobs\RunDatabaseBackupJob;
use Shared\Domain\Ports\AuditPort;
use Tests\TestCase;

uses(TestCase::class);

it('queues the job even when the audit write fails', function (): void {
    Queue::fake();
    $status = new BackupRunStatusData(BackupRunStatus::Queued);

    $this->mock(BackupRunStatusStore::class, function (MockInterface $mock) use ($status): void {
        $mock->shouldReceive('current')->once()->andReturnNull();
        $mock->shouldReceive('markQueued')->once()->andReturn($status);
    });

    $this->mock(AuditPort::class, function (MockInterface $mock): void {
        $mock->shouldReceive('log')->once()->andThrow(new RuntimeException('audit down'));
    });

    expect(app(RunDatabaseBackupAction::class)->execute(null))->toBe($status);

    Queue::assertPushed(RunDatabaseBackupJob::class);
});

it('keeps an active run status when a duplicate request is absorbed', function (): void {
    Queue::fake();
    $status = new BackupRunStatusData(BackupRunStatus::Running);

    $this->mock(BackupRunStatusStore::class, function (MockInterface $mock) use ($status): void {
        $mock->shouldReceive('current')->once()->andReturn($status);
        $mock->shouldNotReceive('markQueued');
    });
    $this->mock(AuditPort::class)->shouldReceive('log')->once();

    expect(app(RunDatabaseBackupAction::class)->execute(null))->toBe($status);
    Queue::assertPushed(RunDatabaseBackupJob::class);
});
