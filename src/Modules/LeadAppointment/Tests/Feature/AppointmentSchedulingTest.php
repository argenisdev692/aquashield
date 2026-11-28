<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Modules\LeadAppointment\Domain\Enums\AppointmentStatus;
use Modules\LeadAppointment\Domain\Enums\LeadStatus;
use Modules\LeadAppointment\Domain\Enums\Region;
use Modules\LeadAppointment\Domain\Events\AppointmentDeleted;
use Modules\LeadAppointment\Domain\Events\AppointmentRestored;
use Modules\LeadAppointment\Infrastructure\Persistence\Eloquent\Models\Appointment;
use Modules\LeadAppointment\Tests\Support\LeadAppointmentFixtures;
use Shared\Domain\Enums\Permission as PermissionName;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    LeadAppointmentFixtures::seedPermissions();
    $this->admin = LeadAppointmentFixtures::superAdmin();
    $this->date = LeadAppointmentFixtures::nextWeekdayAt('08:00');
    LeadAppointmentFixtures::companyRule(1, '08:00', '17:00');
});

/**
 * @return array<string, mixed>
 */
function appointmentPayload(string $leadUuid, string $startsAt, ?string $region = null): array
{
    return [
        'lead_uuid' => $leadUuid,
        'kind' => 'inspection',
        'starts_at' => $startsAt,
        'duration_minutes' => 420,
        'region' => $region,
    ];
}

it('schedules an inspection, advances the lead and writes one appointment', function (): void {
    $lead = LeadAppointmentFixtures::lead();

    $response = $this->actingAs($this->admin)
        ->postJson('/appointments', appointmentPayload(
            $lead->uuid,
            $this->date->format('Y-m-d H:i'),
        ))
        ->assertCreated()
        ->assertJsonPath('kind', 'inspection')
        ->assertJsonPath('status', 'scheduled');

    expect($lead->refresh()->status)->toBe(LeadStatus::InspectionScheduled)
        ->and(Appointment::query()->count())->toBe(1)
        ->and(Appointment::query()->first()?->created_by)->toBe($this->admin->id)
        ->and($response->json('starts_at_local'))->toContain('08:00');
});

it('uses the request region when the lead has no region', function (): void {
    $lead = LeadAppointmentFixtures::lead();
    $lead->region = null;
    $lead->save();

    $this->actingAs($this->admin)
        ->postJson('/appointments', appointmentPayload(
            $lead->uuid,
            $this->date->format('Y-m-d H:i'),
            'dallas',
        ))
        ->assertCreated()
        ->assertJsonPath('region', 'dallas');
});

it('falls back to the configured region when neither lead nor request carries one', function (): void {
    $lead = LeadAppointmentFixtures::lead();
    $lead->region = null;
    $lead->save();

    $this->actingAs($this->admin)
        ->postJson('/appointments', appointmentPayload(
            $lead->uuid,
            $this->date->format('Y-m-d H:i'),
        ))
        ->assertCreated()
        ->assertJsonPath('region', 'houston');
});

it('rejects a past date with 422', function (): void {
    $lead = LeadAppointmentFixtures::lead();
    $past = now('America/Chicago')->subDay()->setTime(10, 0);

    $this->actingAs($this->admin)
        ->postJson('/appointments', appointmentPayload(
            $lead->uuid,
            $past->format('Y-m-d H:i'),
        ))
        ->assertUnprocessable();
});

it('rejects a time outside the availability window', function (): void {
    $lead = LeadAppointmentFixtures::lead();
    $early = $this->date->setTime(6, 0);

    $this->actingAs($this->admin)
        ->postJson('/appointments', appointmentPayload(
            $lead->uuid,
            $early->format('Y-m-d H:i'),
        ))
        ->assertUnprocessable();
});

it('rejects a second inspection on the same day for the single crew', function (): void {
    $firstLead = LeadAppointmentFixtures::lead();
    $secondLead = LeadAppointmentFixtures::lead();

    $this->actingAs($this->admin)
        ->postJson('/appointments', appointmentPayload(
            $firstLead->uuid,
            $this->date->format('Y-m-d H:i'),
        ))
        ->assertCreated();

    $this->actingAs($this->admin)
        ->postJson('/appointments', appointmentPayload(
            $secondLead->uuid,
            $this->date->setTime(16, 0)->format('Y-m-d H:i'),
        ))
        ->assertUnprocessable();
});

it('rejects a timezone that does not belong to the appointment region', function (): void {
    $lead = LeadAppointmentFixtures::lead(Region::Houston);

    $this->actingAs($this->admin)->postJson('/appointments', [
        'lead_uuid' => $lead->uuid,
        'kind' => 'inspection',
        'starts_at' => $this->date->format('Y-m-d H:i'),
        'duration_minutes' => 60,
        'timezone' => 'America/Los_Angeles',
    ])->assertUnprocessable();

    expect(Appointment::query()->where('lead_id', $lead->id)->exists())->toBeFalse();
});

it('allows the documented Chicago timezone for a Florida appointment', function (): void {
    $lead = LeadAppointmentFixtures::lead(Region::Florida);

    $this->actingAs($this->admin)->postJson('/appointments', [
        'lead_uuid' => $lead->uuid,
        'kind' => 'inspection',
        'starts_at' => $this->date->format('Y-m-d H:i'),
        'duration_minutes' => 60,
        'timezone' => 'America/Chicago',
    ])->assertCreated()
        ->assertJsonPath('region', 'florida')
        ->assertJsonPath('timezone', 'America/Chicago');
});

it('persists emergency status through delete restore and reschedule', function (): void {
    $lead = LeadAppointmentFixtures::lead();
    $created = $this->actingAs($this->admin)->postJson('/appointments', [
        'lead_uuid' => $lead->uuid,
        'kind' => 'inspection',
        'starts_at' => $this->date->setTime(18, 0)->format('Y-m-d H:i'),
        'duration_minutes' => 60,
        'is_emergency' => true,
    ])->assertCreated();
    $appointment = Appointment::query()->where('uuid', $created->json('uuid'))->firstOrFail();

    expect($appointment->is_emergency)->toBeTrue();

    $this->actingAs($this->admin)->deleteJson('/appointments/'.$appointment->uuid)->assertNoContent();
    $this->actingAs($this->admin)->patchJson('/appointments/'.$appointment->uuid.'/restore')->assertOk();
    $this->actingAs($this->admin)->postJson('/appointments/'.$appointment->uuid.'/reschedule', [
        'starts_at' => $this->date->setTime(19, 0)->format('Y-m-d H:i'),
        'duration_minutes' => 60,
        'reason' => 'Emergency moved',
    ])->assertOk();

    expect($appointment->refresh()->is_emergency)->toBeTrue();
});

it('dispatches after-commit delete and restore synchronization events', function (): void {
    Event::fake([AppointmentDeleted::class, AppointmentRestored::class]);
    $appointment = Appointment::factory()->create([
        'starts_at' => $this->date->utc(),
        'ends_at' => $this->date->addHour()->utc(),
        'timezone' => 'America/Chicago',
        'region' => 'houston',
    ]);

    $this->actingAs($this->admin)->deleteJson('/appointments/'.$appointment->uuid)->assertNoContent();
    Event::assertDispatched(AppointmentDeleted::class, fn (AppointmentDeleted $event): bool => $event->appointmentUuid === $appointment->uuid);

    $this->actingAs($this->admin)->patchJson('/appointments/'.$appointment->uuid.'/restore')->assertOk();
    Event::assertDispatched(AppointmentRestored::class, fn (AppointmentRestored $event): bool => $event->appointmentUuid === $appointment->uuid);
});

it('checks next-day overlaps for cross-midnight emergency visits', function (): void {
    $nextDayStart = $this->date->addDay()->setTime(7, 30);

    Appointment::factory()->create([
        'starts_at' => $nextDayStart->utc(),
        'ends_at' => $nextDayStart->addHour()->utc(),
        'timezone' => 'America/Chicago',
        'region' => 'houston',
        'status' => AppointmentStatus::Scheduled->value,
    ]);

    $lead = LeadAppointmentFixtures::lead();

    $this->actingAs($this->admin)->postJson('/appointments', [
        'lead_uuid' => $lead->uuid,
        'kind' => 'inspection',
        'starts_at' => $this->date->setTime(23, 0)->format('Y-m-d H:i'),
        'duration_minutes' => 480,
        'is_emergency' => true,
    ])->assertUnprocessable();

    expect(Appointment::query()->where('lead_id', $lead->id)->exists())->toBeFalse();
});

it('keeps a completed inspection in the daily capacity count', function (): void {
    $firstLead = LeadAppointmentFixtures::lead();
    $secondLead = LeadAppointmentFixtures::lead();
    $morning = $this->date->setTime(8, 0)->format('Y-m-d H:i');

    $first = $this->actingAs($this->admin)->postJson('/appointments', [
        'lead_uuid' => $firstLead->uuid,
        'kind' => 'inspection',
        'starts_at' => $morning,
        'duration_minutes' => 60,
    ])->assertCreated();

    $this->actingAs($this->admin)
        ->postJson("/appointments/{$first->json('uuid')}/confirm")
        ->assertOk();

    $this->actingAs($this->admin)
        ->postJson("/appointments/{$first->json('uuid')}/complete")
        ->assertOk();

    $this->actingAs($this->admin)->postJson('/appointments', [
        'lead_uuid' => $secondLead->uuid,
        'kind' => 'inspection',
        'starts_at' => $this->date->setTime(16, 0)->format('Y-m-d H:i'),
        'duration_minutes' => 60,
    ])->assertUnprocessable();
});

it('refuses to restore an appointment into an occupied day', function (): void {
    $firstLead = LeadAppointmentFixtures::lead();
    $secondLead = LeadAppointmentFixtures::lead();

    $first = $this->actingAs($this->admin)->postJson('/appointments', appointmentPayload(
        $firstLead->uuid,
        $this->date->format('Y-m-d H:i'),
    ))->assertCreated();

    $this->actingAs($this->admin)->deleteJson("/appointments/{$first->json('uuid')}")->assertNoContent();

    $second = $this->actingAs($this->admin)->postJson('/appointments', appointmentPayload(
        $secondLead->uuid,
        $this->date->format('Y-m-d H:i'),
    ))->assertCreated();

    $this->actingAs($this->admin)
        ->patchJson("/appointments/{$first->json('uuid')}/restore")
        ->assertUnprocessable();

    expect(Appointment::query()->where('uuid', $first->json('uuid'))->onlyTrashed()->exists())->toBeTrue()
        ->and(Appointment::query()->where('uuid', $second->json('uuid'))->whereNull('deleted_at')->exists())->toBeTrue();
});

it('rolls back the whole bulk restore when one appointment conflicts', function (): void {
    $first = Appointment::factory()->create([
        'starts_at' => $this->date,
        'ends_at' => $this->date->copy()->addHours(7),
        'timezone' => 'America/Chicago',
        'region' => 'houston',
        'status' => AppointmentStatus::Scheduled->value,
    ]);
    $second = Appointment::factory()->create([
        'starts_at' => $this->date->copy()->addWeek(),
        'ends_at' => $this->date->copy()->addWeek()->addHours(7),
        'timezone' => 'America/Chicago',
        'region' => 'houston',
        'status' => AppointmentStatus::Scheduled->value,
    ]);
    $first->delete();
    $second->delete();

    Appointment::factory()->create([
        'starts_at' => $this->date,
        'ends_at' => $this->date->copy()->addHours(7),
        'timezone' => 'America/Chicago',
        'region' => 'houston',
        'status' => AppointmentStatus::Scheduled->value,
    ]);

    $this->actingAs($this->admin)
        ->postJson('/appointments/bulk-restore', ['uuids' => [$second->uuid, $first->uuid]])
        ->assertUnprocessable();

    expect(Appointment::query()->where('uuid', $first->uuid)->onlyTrashed()->exists())->toBeTrue()
        ->and(Appointment::query()->where('uuid', $second->uuid)->onlyTrashed()->exists())->toBeTrue();
});

it('cancels an appointment and frees the status', function (): void {
    $lead = LeadAppointmentFixtures::lead();

    $created = $this->actingAs($this->admin)
        ->postJson('/appointments', appointmentPayload(
            $lead->uuid,
            $this->date->format('Y-m-d H:i'),
        ))
        ->assertCreated();

    $this->actingAs($this->admin)
        ->postJson('/appointments/'.$created->json('uuid').'/cancel', ['reason' => 'Weather'])
        ->assertOk()
        ->assertJsonPath('status', AppointmentStatus::Cancelled->value)
        ->assertJsonPath('cancel_reason', 'Weather');

    $this->actingAs($this->admin)
        ->postJson('/appointments/'.$created->json('uuid').'/cancel', ['reason' => 'Duplicate'])
        ->assertUnprocessable();
});

it('answers 401 to a guest and 403 without CREATE_APPOINTMENT', function (): void {
    $this->postJson('/appointments', [])->assertUnauthorized();

    $this->actingAs(User::factory()->create())
        ->postJson('/appointments', [])
        ->assertForbidden();
});

it('forbids appointment reads and transitions without the permission', function (): void {
    $appointment = Appointment::factory()->create();
    $outsider = User::factory()->create();

    $this->actingAs($outsider)->getJson('/appointments/'.$appointment->uuid)->assertForbidden();
    $this->actingAs($outsider)->deleteJson('/appointments/'.$appointment->uuid)->assertForbidden();
    $this->actingAs($outsider)->patchJson('/appointments/'.$appointment->uuid.'/restore')->assertForbidden();
    $this->actingAs($outsider)->postJson('/appointments/bulk-delete', ['uuids' => [$appointment->uuid]])->assertForbidden();
    $this->actingAs($outsider)->postJson('/appointments/bulk-restore', ['uuids' => [$appointment->uuid]])->assertForbidden();
    $this->actingAs($outsider)->postJson('/appointments/'.$appointment->uuid.'/confirm')->assertForbidden();
    $this->actingAs($outsider)->postJson('/appointments/'.$appointment->uuid.'/cancel', ['reason' => 'Nope'])->assertForbidden();
    $this->actingAs($outsider)->postJson('/appointments/'.$appointment->uuid.'/complete')->assertForbidden();
    $this->actingAs($outsider)->postJson('/appointments/'.$appointment->uuid.'/reschedule', [
        'starts_at' => $this->date->format('Y-m-d H:i'),
        'duration_minutes' => 60,
        'reason' => 'Nope',
    ])->assertForbidden();
});

it('requires VIEW_APPOINTMENT for PII-bearing appointment mutations and edit screen', function (): void {
    $appointment = Appointment::factory()->create();
    $scheduler = User::factory()->create();
    $scheduler->givePermissionTo([
        PermissionName::CreateAppointment->value,
        PermissionName::UpdateAppointment->value,
    ]);

    $this->actingAs($scheduler)->postJson('/appointments', [])->assertForbidden();
    $this->actingAs($scheduler)->get('/appointments/'.$appointment->uuid.'/edit')->assertForbidden();
    $this->actingAs($scheduler)->postJson('/appointments/'.$appointment->uuid.'/confirm')->assertForbidden();
    $this->actingAs($scheduler)->postJson('/appointments/'.$appointment->uuid.'/cancel', ['reason' => 'No access'])->assertForbidden();
});

it('returns the calendar feed with availability windows and appointments', function (): void {
    $lead = LeadAppointmentFixtures::lead();

    $this->actingAs($this->admin)
        ->postJson('/appointments', appointmentPayload(
            $lead->uuid,
            $this->date->format('Y-m-d H:i'),
        ))
        ->assertCreated();

    $this->actingAs($this->admin)
        ->getJson('/appointments/calendar?'.http_build_query([
            'from' => $this->date->startOfWeek()->toDateString(),
            'to' => $this->date->endOfWeek()->toDateString(),
            'region' => 'houston',
        ]))
        ->assertOk()
        ->assertJsonPath('timezone', 'America/Chicago')
        ->assertJsonCount(7, 'days');
});

it('applies the configured maximum as inclusive calendar feed days', function (): void {
    config(['lead-appointment.calendar_max_days' => 2]);

    $this->actingAs($this->admin)
        ->getJson('/appointments/calendar?from=2026-01-01&to=2026-01-02')
        ->assertOk();

    $this->actingAs($this->admin)
        ->getJson('/appointments/calendar?from=2026-01-01&to=2026-01-03')
        ->assertUnprocessable();
});

it('exports appointments as CSV and PDF', function (): void {
    $lead = LeadAppointmentFixtures::lead();

    $this->actingAs($this->admin)
        ->postJson('/appointments', appointmentPayload(
            $lead->uuid,
            $this->date->format('Y-m-d H:i'),
        ))
        ->assertCreated();

    $csv = $this->actingAs($this->admin)
        ->get('/appointments/export/csv')
        ->assertOk()
        ->streamedContent();

    expect($csv)
        ->toContain('Customer')
        ->toContain($lead->first_name);

    $pdf = $this->actingAs($this->admin)
        ->get('/appointments/export/pdf')
        ->assertOk();

    expect($pdf->getContent())->toStartWith('%PDF');
});
