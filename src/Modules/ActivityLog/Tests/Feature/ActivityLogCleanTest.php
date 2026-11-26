<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\ActivityLog\Actions\ChunkedCleanActivityLogAction;
use Modules\ActivityLog\Models\ActivityEntry;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('deletes only rows older than the retention window in chunks', function (): void {
    DB::table('activity_log')->insert([
        'uuid' => (string) Str::uuid7(),
        'log_name' => 'backups',
        'description' => 'old',
        'created_at' => now()->subYears(10),
        'updated_at' => now()->subYears(10),
    ]);

    activity('backups')->log('recent');

    $deleted = app(ChunkedCleanActivityLogAction::class)->execute(3650);

    expect($deleted)->toBe(1)
        ->and(ActivityEntry::query()->count())->toBe(1)
        ->and(ActivityEntry::query()->firstOrFail()->description)->toBe('recent');
});
