<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Queue;
use Mockery\MockInterface;
use Modules\Backups\Application\Actions\RunDatabaseBackupAction;
use Modules\Backups\Jobs\RunDatabaseBackupJob;
use Shared\Domain\Ports\AuditPort;
use Tests\TestCase;

uses(TestCase::class);

/*
 * Laravel processes a job twice when the worker timeout outlives the queue
 * reservation (`retry_after`). The dump is the longest job in the app, so it
 * is the one that sets the floor.
 */
it('keeps the queue reservation longer than the database dump may run', function (string $connection): void {
    $job = new RunDatabaseBackupJob;

    expect((int) config("queue.connections.{$connection}.retry_after"))
        ->toBeGreaterThan($job->timeout);
})->with(['database', 'redis']);

it('routes the dump to the heavy queue', function (): void {
    Queue::fake();

    $this->mock(AuditPort::class, function (MockInterface $mock): void {
        $mock->shouldReceive('log')->once();
    });

    app(RunDatabaseBackupAction::class)->execute(null);

    // `worker` consumes `default`; `worker-heavy` consumes this queue, so the
    // dump cannot block invitations or password resets (README, supervisord).
    Queue::assertPushed(
        RunDatabaseBackupJob::class,
        fn (RunDatabaseBackupJob $job): bool => $job->queue === RunDatabaseBackupJob::QUEUE,
    );
});
