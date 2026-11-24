<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Modules\Backups\Application\Ports\BackupArchiveStore;
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

it('reports an empty quarantine before anything is quarantined', function (): void {
    // The listing directory does not exist yet; the adapter must answer with an
    // empty list rather than failing the index page.
    Storage::disk('local')->put('test-backups/2026-10-01-00-00-00.zip', 'PAYLOAD');

    expect($this->store->trashed())->toBe([]);
});

it('derives an archive id that is stable but not computable from the path', function (): void {
    Storage::disk('local')->put('test-backups/2026-10-01-00-00-00.zip', 'PAYLOAD');

    $first = $this->store->active()[0]->uuid;

    // Same deployment, same path: the id must survive a cache flush, otherwise
    // a deep link breaks between two requests.
    Cache::flush();
    expect($this->store->active()[0]->uuid)->toBe($first)
        ->and(Str::isUuid($first))->toBeTrue();

    // Different APP_KEY, same path: a different id. Filenames are predictable
    // timestamps, so the id must not be derivable without the secret.
    Cache::flush();
    config()->set('app.key', 'base64:'.base64_encode(random_bytes(32)));

    expect($this->store->active()[0]->uuid)->not->toBe($first);
});

it('moves an archive to quarantine and restores it', function (): void {
    Storage::disk('local')->put('test-backups/2026-10-01-00-00-00.zip', 'PAYLOAD');

    $active = $this->store->active()[0];
    $this->store->quarantine($active);

    expect($this->store->active())->toBe([])
        ->and($this->store->trashed())->toHaveCount(1)
        ->and($this->store->trashed()[0]->sizeBytes)->toBe(7)
        ->and($this->store->trashed()[0]->takenAt->format('Y-m-d H:i:s'))->toBe('2026-10-01 00:00:00')
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
