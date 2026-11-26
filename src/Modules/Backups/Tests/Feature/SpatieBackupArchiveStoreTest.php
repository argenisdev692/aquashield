<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Modules\Backups\Application\Ports\BackupArchiveStore;
use Modules\Backups\Domain\Exceptions\BackupDestinationUnavailable;
use Modules\Backups\Domain\Exceptions\BackupMoveInProgress;
use Modules\Backups\Domain\Exceptions\BackupNotFound;
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

it('caches a scalar payload instead of domain objects', function (): void {
    Storage::disk('local')->put('test-backups/2026-10-01-00-00-00.zip', 'PAYLOAD');

    $this->store->active();

    /** @var array<int, array<string, mixed>> $cached */
    $cached = Cache::get('backups.inventory.testing.active.v2');

    expect($cached)->toBeArray()
        ->and($cached[0])->toBeArray()
        ->and($cached[0]['filename'])->toBe('2026-10-01-00-00-00.zip')
        ->and($cached[0]['taken_at'])->toBeString();
});

it('hydrates a payload that crossed a store with classes disallowed', function (): void {
    Storage::disk('local')->put('test-backups/2026-10-01-00-00-00.zip', 'PAYLOAD');

    $this->store->active();

    $key = 'backups.inventory.testing.active.v2';

    // Exactly what Redis/database returns when `serializable_classes` is
    // false: the array survives, but nothing inside it may be an object.
    Cache::put($key, unserialize(serialize(Cache::get($key)), ['allowed_classes' => false]), 30);

    $archives = $this->store->active();

    expect($archives)->toHaveCount(1)
        ->and($archives[0]->filename)->toBe('2026-10-01-00-00-00.zip')
        ->and($archives[0]->takenAt->format('Y-m-d H:i:s'))->toBe('2026-10-01 00:00:00');
});

it('reports an unreachable destination instead of an empty inventory', function (): void {
    config()->set('backup.backup.destination.disks', ['missing-disk']);

    expect(fn () => $this->store->active())->toThrow(BackupDestinationUnavailable::class);
    expect(fn () => $this->store->trashed())->toThrow(BackupDestinationUnavailable::class);
});

it('ignores a malformed cached payload instead of trusting it', function (): void {
    Storage::disk('local')->put('test-backups/2026-10-01-00-00-00.zip', 'PAYLOAD');

    Cache::put('backups.inventory.testing.active.v2', [['uuid' => 'not-an-archive']], 30);

    $archives = $this->store->active();

    expect($archives)->toHaveCount(1)
        ->and($archives[0]->filename)->toBe('2026-10-01-00-00-00.zip');
});

it('reports a vanished active source as not found instead of a storage error', function (): void {
    Storage::disk('local')->put('test-backups/2026-10-01-00-00-00.zip', 'PAYLOAD');
    $archive = $this->store->active()[0];
    Storage::disk('local')->delete($archive->path);

    expect(fn () => $this->store->quarantine($archive))->toThrow(BackupNotFound::class);
});

it('reports a vanished quarantined source as not found instead of a storage error', function (): void {
    Storage::disk('local')->put('test-backups/2026-10-01-00-00-00.zip', 'PAYLOAD');
    $archive = $this->store->active()[0];
    $this->store->quarantine($archive);
    $quarantined = $this->store->trashed()[0];
    Storage::disk('local')->delete($quarantined->path);

    expect(fn () => $this->store->restore($quarantined))->toThrow(BackupNotFound::class);
});

it('refuses a move while another request holds the file lock', function (): void {
    Storage::disk('local')->put('test-backups/2026-10-01-00-00-00.zip', 'PAYLOAD');
    $archive = $this->store->active()[0];

    $lock = Cache::lock('backups:move:local:2026-10-01-00-00-00.zip', 300);
    expect($lock->get())->toBeTrue();

    try {
        expect(fn () => $this->store->quarantine($archive))->toThrow(BackupMoveInProgress::class);
    } finally {
        $lock->release();
    }

    expect($this->store->active())->toHaveCount(1);
});
