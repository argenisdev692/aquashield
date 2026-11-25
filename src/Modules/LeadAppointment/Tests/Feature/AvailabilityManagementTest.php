<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\LeadAppointment\Infrastructure\Persistence\Eloquent\Models\AvailabilityException;
use Modules\LeadAppointment\Infrastructure\Persistence\Eloquent\Models\AvailabilityRule;
use Modules\LeadAppointment\Tests\Support\LeadAppointmentFixtures;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    LeadAppointmentFixtures::seedPermissions();
    $this->admin = LeadAppointmentFixtures::superAdmin();
});

it('creates and lists a weekly availability rule', function (): void {
    $created = $this->actingAs($this->admin)->postJson('/availability-rules', [
        'day_of_week' => 1,
        'start_time' => '08:00',
        'end_time' => '17:00',
        'is_available' => true,
    ])->assertCreated()->assertJsonPath('day_of_week', 1);

    $this->actingAs($this->admin)
        ->getJson('/availability-rules')
        ->assertOk()
        ->assertJsonPath('data.0.uuid', $created->json('uuid'));
});

it('creates a date exception and a bulk closure range', function (): void {
    $this->actingAs($this->admin)->postJson('/availability-exceptions', [
        'date' => '2026-12-24',
        'is_available' => false,
        'reason' => 'Christmas Eve',
    ])->assertCreated()->assertJsonPath('date', '2026-12-24');

    $this->actingAs($this->admin)->postJson('/availability-exceptions/bulk-range', [
        'from' => '2026-12-24',
        'to' => '2026-12-26',
        'is_available' => false,
        'reason' => 'Holiday shutdown',
    ])->assertCreated()->assertJsonPath('created', 3);

    expect(AvailabilityException::query()->count())->toBe(3);
});

it('materialises US federal holidays and keeps manual rows', function (): void {
    $manual = AvailabilityException::factory()->create([
        'date' => '2026-07-04',
        'is_available' => true,
        'reason' => 'Crew works Independence Day',
        'source' => 'manual',
    ]);

    $this->actingAs($this->admin)
        ->postJson('/availability/sync-holidays')
        ->assertOk()
        ->assertJsonStructure(['purged', 'created']);

    expect($manual->refresh()->source->value)->toBe('manual')
        ->and($manual->is_available)->toBeTrue();
});

it('bounds the availability exceptions JSON window', function (): void {
    $this->actingAs($this->admin)
        ->getJson('/availability-exceptions?from=2026-01-01&to=2026-12-31')
        ->assertUnprocessable();

    $this->actingAs($this->admin)
        ->getJson('/availability-exceptions')
        ->assertOk();
});

it('returns the resolved availability calendar', function (): void {
    LeadAppointmentFixtures::companyRule(1, '08:00', '17:00');

    $this->actingAs($this->admin)
        ->getJson('/availability/calendar?from=2026-01-05&to=2026-01-11')
        ->assertOk()
        ->assertJsonCount(7, 'data')
        ->assertJsonPath('data.0.date', '2026-01-05')
        ->assertJsonPath('data.0.is_open', true);
});

it('applies an exception on the last day of the calendar window', function (): void {
    LeadAppointmentFixtures::companyRule(0, '08:00', '17:00');

    AvailabilityException::factory()->create([
        'date' => '2026-01-11',
        'is_available' => false,
        'reason' => 'Window end closure',
        'source' => 'manual',
    ]);

    $this->actingAs($this->admin)
        ->getJson('/availability/calendar?from=2026-01-05&to=2026-01-11')
        ->assertOk()
        ->assertJsonPath('data.6.date', '2026-01-11')
        ->assertJsonPath('data.6.is_open', false)
        ->assertJsonPath('data.6.source', 'company_exception');
});

it('rejects a duplicate active exception with 422', function (): void {
    $payload = [
        'date' => '2026-12-24',
        'is_available' => false,
        'reason' => 'Christmas Eve',
    ];

    $this->actingAs($this->admin)
        ->postJson('/availability-exceptions', $payload)
        ->assertCreated();

    $this->actingAs($this->admin)
        ->postJson('/availability-exceptions', $payload)
        ->assertStatus(422);
});

it('rejects restoring an exception that collides with an active one', function (): void {
    $original = AvailabilityException::factory()->create([
        'date' => '2026-12-25',
        'is_available' => false,
    ]);
    $original->delete();

    AvailabilityException::factory()->create([
        'date' => '2026-12-25',
        'is_available' => false,
    ]);

    $this->actingAs($this->admin)
        ->patchJson("/availability-exceptions/{$original->uuid}/restore")
        ->assertStatus(422);
});

it('answers 401 and 403 for availability management', function (): void {
    $this->getJson('/availability-rules')->assertUnauthorized();

    $this->actingAs(User::factory()->create())
        ->getJson('/availability-rules')
        ->assertForbidden();
});

it('forbids availability mutations without the matching permission', function (): void {
    $rule = AvailabilityRule::factory()->create();
    $exception = AvailabilityException::factory()->create();
    $outsider = User::factory()->create();

    $this->actingAs($outsider)->putJson('/availability-rules/'.$rule->uuid, [])->assertForbidden();
    $this->actingAs($outsider)->deleteJson('/availability-rules/'.$rule->uuid)->assertForbidden();
    $this->actingAs($outsider)->patchJson('/availability-rules/'.$rule->uuid.'/restore')->assertForbidden();
    $this->actingAs($outsider)->putJson('/availability-exceptions/'.$exception->uuid, [])->assertForbidden();
    $this->actingAs($outsider)->deleteJson('/availability-exceptions/'.$exception->uuid)->assertForbidden();
    $this->actingAs($outsider)->patchJson('/availability-exceptions/'.$exception->uuid.'/restore')->assertForbidden();
    $this->actingAs($outsider)->postJson('/availability-exceptions/bulk-range', [])->assertForbidden();
    $this->actingAs($outsider)->postJson('/availability/sync-holidays')->assertForbidden();
});
