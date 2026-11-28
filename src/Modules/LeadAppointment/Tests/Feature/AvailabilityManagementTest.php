<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\LeadAppointment\Domain\Enums\AvailabilitySource;
use Modules\LeadAppointment\Infrastructure\Persistence\Eloquent\Models\AvailabilityException;
use Modules\LeadAppointment\Infrastructure\Persistence\Eloquent\Models\AvailabilityRule;
use Modules\LeadAppointment\Tests\Support\LeadAppointmentFixtures;
use Shared\Domain\Enums\Permission as PermissionName;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    LeadAppointmentFixtures::seedPermissions();
    $this->admin = LeadAppointmentFixtures::superAdmin();
});

it('enforces active and per-weekday rule limits', function (): void {
    config([
        'lead-appointment.availability_rules.max_active' => 2,
        'lead-appointment.availability_rules.max_per_weekday' => 2,
    ]);

    $payload = [
        'day_of_week' => 1,
        'start_time' => '08:00',
        'end_time' => '12:00',
        'is_available' => true,
    ];

    $this->actingAs($this->admin)->postJson('/availability-rules', $payload)->assertCreated();
    $this->actingAs($this->admin)->postJson('/availability-rules', [
        ...$payload,
        'start_time' => '13:00',
        'end_time' => '17:00',
    ])->assertCreated();

    $this->actingAs($this->admin)->postJson('/availability-rules', [
        ...$payload,
        'start_time' => '17:00',
        'end_time' => '18:00',
    ])->assertUnprocessable();

    $this->actingAs($this->admin)->postJson('/availability-rules', [
        ...$payload,
        'day_of_week' => 2,
    ])->assertUnprocessable();

    expect(AvailabilityRule::query()->count())->toBe(2);
});

it('enforces rule capacity when restoring a deleted window', function (): void {
    config([
        'lead-appointment.availability_rules.max_active' => 1,
        'lead-appointment.availability_rules.max_per_weekday' => 5,
    ]);

    $deleted = AvailabilityRule::factory()->create(['day_of_week' => 1]);
    $deleted->delete();
    AvailabilityRule::factory()->create(['day_of_week' => 1]);

    $this->actingAs($this->admin)
        ->patchJson("/availability-rules/{$deleted->uuid}/restore")
        ->assertUnprocessable();

    expect($deleted->refresh()->trashed())->toBeTrue();
});

it('throttles the availability-rule surface', function (): void {
    config(['lead-appointment.availability_rules.throttle_per_minute' => 2]);

    $this->actingAs($this->admin)->getJson('/availability-rules')->assertOk();
    $this->actingAs($this->admin)->getJson('/availability-rules')->assertOk();
    $this->actingAs($this->admin)->getJson('/availability-rules')->assertTooManyRequests();
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

it('creates a bulk closure range without overwriting existing dates', function (): void {
    $this->actingAs($this->admin)->postJson('/availability-exceptions/bulk-range', [
        'from' => '2026-12-27',
        'to' => '2026-12-29',
        'is_available' => false,
        'reason' => 'Holiday shutdown',
    ])->assertCreated()->assertJsonPath('created', 3);

    expect(AvailabilityException::query()->count())->toBe(3);
});

it('rejects a bulk range that touches an existing exception', function (): void {
    AvailabilityException::factory()->create([
        'date' => '2026-12-24',
        'is_available' => false,
        'reason' => 'Christmas Eve',
    ]);

    $this->actingAs($this->admin)->postJson('/availability-exceptions/bulk-range', [
        'from' => '2026-12-24',
        'to' => '2026-12-26',
        'is_available' => false,
        'reason' => 'Holiday shutdown',
    ])->assertUnprocessable();

    expect(AvailabilityException::query()->count())->toBe(1)
        ->and(AvailabilityException::query()->sole()->reason)->toBe('Christmas Eve');
});

it('promotes an edited holiday to a durable manual override', function (): void {
    $holiday = AvailabilityException::factory()->create([
        'date' => '2026-12-25',
        'is_available' => false,
        'reason' => 'Christmas Day',
        'source' => AvailabilitySource::Holiday->value,
    ]);

    $this->actingAs($this->admin)
        ->putJson("/availability-exceptions/{$holiday->uuid}", [
            'date' => '2026-12-25',
            'is_available' => true,
            'start_time' => '09:00',
            'end_time' => '13:00',
            'reason' => 'Crew works Christmas by approval',
        ])
        ->assertOk();

    expect($holiday->refresh()->source->value)->toBe('manual');

    $this->actingAs($this->admin)->postJson('/availability/sync-holidays')->assertOk();

    expect($holiday->refresh()->source->value)->toBe('manual')
        ->and($holiday->is_available)->toBeTrue()
        ->and($holiday->reason)->toBe('Crew works Christmas by approval');
});

it('requires delete authority to purge and rebuild holidays', function (): void {
    $creator = User::factory()->create();
    $creator->givePermissionTo(PermissionName::CreateAvailabilityException->value);

    $this->actingAs($creator)
        ->postJson('/availability/sync-holidays')
        ->assertForbidden();
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

it('enforces the inclusive calendar-day cap', function (): void {
    $this->actingAs($this->admin)
        ->getJson('/availability-exceptions?from=2026-01-01&to=2026-04-02')
        ->assertOk();

    $this->actingAs($this->admin)
        ->getJson('/availability-exceptions?from=2026-01-01&to=2026-04-03')
        ->assertUnprocessable();
});

it('rejects non-canonical pagination integers', function (): void {
    $this->actingAs($this->admin)->getJson('/availability?page=1.5')->assertUnprocessable();
    $this->actingAs($this->admin)->getJson('/availability?per_page=10junk')->assertUnprocessable();
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

it('rolls back a bulk exception restore when one date collides', function (): void {
    $collision = AvailabilityException::factory()->create(['date' => '2026-12-24']);
    $collision->delete();
    $other = AvailabilityException::factory()->create(['date' => '2026-12-26']);
    $other->delete();

    AvailabilityException::factory()->create(['date' => '2026-12-24']);

    $this->actingAs($this->admin)
        ->postJson('/availability-exceptions/bulk-restore', ['uuids' => [$other->uuid, $collision->uuid]])
        ->assertUnprocessable();

    expect(AvailabilityException::query()->where('uuid', $other->uuid)->onlyTrashed()->exists())->toBeTrue()
        ->and(AvailabilityException::query()->where('uuid', $collision->uuid)->onlyTrashed()->exists())->toBeTrue();
});

it('keeps valid holiday rows stable and purges only stale holiday rows', function (): void {
    $valid = AvailabilityException::factory()->create([
        'date' => '2026-12-25',
        'source' => AvailabilitySource::Holiday->value,
        'reason' => 'Christmas Day',
    ]);
    $stale = AvailabilityException::factory()->create([
        'date' => '2027-01-02',
        'source' => AvailabilitySource::Holiday->value,
        'reason' => 'Stale holiday',
    ]);
    $validUuid = $valid->uuid;

    $this->actingAs($this->admin)->postJson('/availability/sync-holidays')->assertOk();

    expect($valid->refresh()->trashed())->toBeFalse()
        ->and($valid->uuid)->toBe($validUuid)
        ->and($stale->refresh()->trashed())->toBeTrue();
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
