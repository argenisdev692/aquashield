<?php

declare(strict_types=1);

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\ActivityLog\Models\ActivityEntry;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('requires a uuid on every activity row', function (): void {
    expect(fn () => DB::table('activity_log')->insert([
        'log_name' => 'backups',
        'description' => 'orphan',
        'uuid' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ]))->toThrow(QueryException::class);
});

it('keeps the uuid column unique', function (): void {
    $index = collect(Schema::getIndexes('activity_log'))
        ->first(fn (array $index): bool => $index['columns'] === ['uuid']);

    expect($index)->not->toBeNull()
        ->and($index['unique'])->toBeTrue();
});

it('refuses to update or delete an activity entry through the model', function (): void {
    activity('backups')->event('backup.quarantined')->log('quarantined');

    $entry = ActivityEntry::query()->firstOrFail();

    expect(fn () => $entry->update(['description' => 'rewritten']))
        ->toThrow(LogicException::class);

    expect(fn () => $entry->delete())
        ->toThrow(LogicException::class);
});
