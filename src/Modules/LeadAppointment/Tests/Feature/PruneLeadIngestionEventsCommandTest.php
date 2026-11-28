<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\LeadAppointment\Infrastructure\Persistence\Eloquent\Models\LeadIngestionEvent;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

function ingestionEvent(?string $processedAt): LeadIngestionEvent
{
    return LeadIngestionEvent::query()->create([
        'provider' => 'meta',
        'external_id' => 'leadgen-'.uniqid(),
        'payload' => ['field_data' => []],
        'status' => $processedAt === null ? 'failed' : 'processed',
        'received_at' => now(),
        'processed_at' => $processedAt,
    ]);
}

it('prunes only processed ingestion events older than the retention window', function (): void {
    $old = ingestionEvent(now()->subDays(40)->toDateTimeString());
    $recent = ingestionEvent(now()->subDays(5)->toDateTimeString());
    $failed = ingestionEvent(null);

    $this->artisan('lead-appointment:prune-ingestion-events', ['--days' => 30])
        ->assertSuccessful();

    expect(LeadIngestionEvent::query()->whereKey($old->id)->exists())->toBeFalse()
        ->and(LeadIngestionEvent::query()->whereKey($recent->id)->exists())->toBeTrue()
        ->and(LeadIngestionEvent::query()->whereKey($failed->id)->exists())->toBeTrue();
});
