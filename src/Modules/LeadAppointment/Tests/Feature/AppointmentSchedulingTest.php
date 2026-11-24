<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\LeadAppointment\Domain\Enums\AppointmentStatus;
use Modules\LeadAppointment\Domain\Enums\LeadStatus;
use Modules\LeadAppointment\Infrastructure\Persistence\Eloquent\Models\Appointment;
use Modules\LeadAppointment\Tests\Support\LeadAppointmentFixtures;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    LeadAppointmentFixtures::seedPermissions();
    $this->admin = LeadAppointmentFixtures::superAdmin();
    $this->date = LeadAppointmentFixtures::nextWeekdayAt('10:00');
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
        ->and($response->json('starts_at_local'))->toContain('10:00');
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
