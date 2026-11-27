<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Cache;
use Modules\Backups\Domain\Enums\BackupRunStatus;
use Modules\Backups\Infrastructure\Support\CacheBackupRunStatusStore;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(fn () => Cache::flush());
afterEach(fn () => Cache::flush());

it('stores the latest backup run status without exposing raw cache payloads', function (): void {
    $store = new CacheBackupRunStatusStore;

    expect($store->current())->toBeNull();

    $queued = $store->markQueued();
    expect($queued->status)->toBe(BackupRunStatus::Queued)
        ->and($store->current()?->status)->toBe(BackupRunStatus::Queued);

    $store->update(BackupRunStatus::Running);
    expect($store->current()?->status)->toBe(BackupRunStatus::Running)
        ->and($store->current()?->updatedAt)->not->toBeNull();
});
