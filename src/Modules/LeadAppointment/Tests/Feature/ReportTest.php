<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\LeadAppointment\Infrastructure\Persistence\Eloquent\Models\Lead;
use Modules\LeadAppointment\Tests\Support\LeadAppointmentFixtures;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    LeadAppointmentFixtures::seedPermissions();
    $this->admin = LeadAppointmentFixtures::superAdmin();
});

it('answers 401 to a guest and 403 without VIEW_ANY_LEAD', function (): void {
    $this->getJson('/reports/lead-sources')->assertUnauthorized();

    $this->actingAs(User::factory()->create())
        ->getJson('/reports/lead-sources')
        ->assertForbidden();
});

it('rejects an invalid date window with 422', function (): void {
    $this->actingAs($this->admin)
        ->getJson('/reports/lead-sources?from=not-a-date')
        ->assertUnprocessable();
});

it('rejects a report window larger than the configured maximum', function (): void {
    $this->actingAs($this->admin)
        ->getJson('/reports/lead-sources?from=2020-01-01&to=2026-01-01')
        ->assertUnprocessable();
});

it('reports a per-source first-contact average, not one global value', function (): void {
    $createdAt = now()->subHour();

    Lead::factory()->create([
        'source' => 'website',
        'created_at' => $createdAt,
        'last_contacted_at' => $createdAt->copy()->addMinutes(10),
    ]);

    Lead::factory()->create([
        'source' => 'meta',
        'created_at' => $createdAt,
        'last_contacted_at' => $createdAt->copy()->addMinutes(30),
    ]);

    $response = $this->actingAs($this->admin)
        ->getJson('/reports/lead-sources?'.http_build_query([
            'from' => now()->subDay()->toDateString(),
            'to' => now()->addDay()->toDateString(),
        ]))
        ->assertOk();

    $rows = collect($response->json('rows'))->keyBy('source');

    expect($rows['website']['average_first_contact_minutes'])->toBe(10)
        ->and($rows['meta']['average_first_contact_minutes'])->toBe(30)
        ->and($response->json('average_first_contact_minutes'))->toBe(20);
});

it('reports active leads and excludes soft-deleted ones', function (): void {
    Lead::factory()->create();

    $deleted = Lead::factory()->create();
    $deleted->delete();

    $this->actingAs($this->admin)
        ->getJson('/reports/lead-sources?'.http_build_query([
            'from' => now()->subDay()->toDateString(),
            'to' => now()->addDay()->toDateString(),
        ]))
        ->assertOk()
        ->assertJsonCount(1, 'rows')
        ->assertJsonPath('rows.0.leads', 1);
});
