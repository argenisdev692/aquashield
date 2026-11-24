<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\LeadAppointment\Domain\Enums\AvailabilitySource;
use Modules\LeadAppointment\Infrastructure\Persistence\Eloquent\Models\AvailabilityException;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('materialises the US federal holidays through the scheduled command', function (): void {
    $this->artisan('lead-appointment:sync-holidays', ['--year' => 2026])
        ->assertSuccessful();

    expect(AvailabilityException::query()->where('source', AvailabilitySource::Holiday->value)->count())
        ->toBe(11);
});

it('is idempotent and preserves manual overrides', function (): void {
    AvailabilityException::factory()->create([
        'date' => '2026-07-03',
        'is_available' => true,
        'reason' => 'Crew works the observed Independence Day',
        'source' => AvailabilitySource::Manual->value,
    ]);

    $this->artisan('lead-appointment:sync-holidays', ['--year' => 2026])->assertSuccessful();
    $this->artisan('lead-appointment:sync-holidays', ['--year' => 2026])->assertSuccessful();

    expect(AvailabilityException::query()->where('source', AvailabilitySource::Holiday->value)->count())
        ->toBe(10);
});
