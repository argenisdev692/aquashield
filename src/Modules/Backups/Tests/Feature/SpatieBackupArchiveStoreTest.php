<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Modules\Backups\Application\Repositories\BackupArchiveStore;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function (): void {
    Storage::fake('local');
    Cache::flush();

    config()->set('backup.backup.name', 'test-backups');
    config()->set('backup.backup.destination.disks', ['local']);
    config()->set('backup.quarantine.directory', '.quarantine');

    $this->store = app(BackupArchiveStore::class);
});

it('lists database archives from the configured destination', function (): void {
    Storage::disk('local')->put('test-backups/2026-10-01-00-00-00.zip', 'PAYLOAD');

    $archives = $this->store->active();

    expect($archives)->toHaveCount(1)
        ->and($archives[0]->filename)->toBe('2026-10-01-00-00-00.zip')
        ->and($archives[0]->disk)->toBe('local')
        ->and($archives[0]->sizeBytes)->toBe(7)
        ->and($archives[0]->takenAt->format('Y-m-d H:i:s'))->toBe('2026-10-01 00:00:00')
        ->and($archives[0]->trashed)->toBeFalse();
});

it('moves an archive to quarantine and restores it', function (): void {
    Storage::disk('local')->put('test-backups/2026-10-01-00-00-00.zip', 'PAYLOAD');

    $active = $this->store->active()[0];
    $this->store->quarantine($active);

    expect($this->store->active())->toBe([])
        ->and($this->store->trashed())->toHaveCount(1)
        ->and(Storage::disk('local')->exists('.quarantine/test-backups/2026-10-01-00-00-00.zip'))->toBeTrue()
        ->and(Storage::disk('local')->exists('test-backups/2026-10-01-00-00-00.zip'))->toBeFalse();

    $quarantined = $this->store->trashed()[0];
    $this->store->restore($quarantined);

    expect($this->store->active())->toHaveCount(1)
        ->and($this->store->trashed())->toBe([])
        ->and(Storage::disk('local')->exists('test-backups/2026-10-01-00-00-00.zip'))->toBeTrue();
});

it('purges only the quarantined copy', function (): void {
    Storage::disk('local')->put('test-backups/2026-10-01-00-00-00.zip', 'PAYLOAD');
    Storage::disk('local')->put('test-backups/2026-10-02-00-00-00.zip', 'PAYLOAD');

    $older = collect($this->store->active())
        ->firstWhere('filename', '2026-10-01-00-00-00.zip');

    $this->store->quarantine($older);
    $this->store->purge($this->store->trashed()[0]);

    expect($this->store->trashed())->toBe([])
        ->and(Storage::disk('local')->exists('.quarantine/test-backups/2026-10-01-00-00-00.zip'))->toBeFalse()
        ->and(Storage::disk('local')->exists('test-backups/2026-10-02-00-00-00.zip'))->toBeTrue();
});

it('ignores files that are not zip archives', function (): void {
    Storage::disk('local')->put('test-backups/notes.txt', 'not a dump');
    Storage::disk('local')->put('test-backups/2026-10-01-00-00-00.zip', 'PAYLOAD');

    expect($this->store->active())->toHaveCount(1);
});
