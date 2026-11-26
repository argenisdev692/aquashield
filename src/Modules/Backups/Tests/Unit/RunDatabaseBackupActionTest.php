<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Queue;
use Mockery\MockInterface;
use Modules\Backups\Application\Actions\RunDatabaseBackupAction;
use Modules\Backups\Jobs\RunDatabaseBackupJob;
use Shared\Domain\Ports\AuditPort;
use Tests\TestCase;

uses(TestCase::class);

it('queues the job even when the audit write fails', function (): void {
    Queue::fake();

    $this->mock(AuditPort::class, function (MockInterface $mock): void {
        $mock->shouldReceive('log')->once()->andThrow(new RuntimeException('audit down'));
    });

    app(RunDatabaseBackupAction::class)->execute(null);

    Queue::assertPushed(RunDatabaseBackupJob::class);
});
