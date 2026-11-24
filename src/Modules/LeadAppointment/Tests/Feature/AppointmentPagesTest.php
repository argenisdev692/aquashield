<?php

declare(strict_types=1);

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;
use Modules\LeadAppointment\Domain\Enums\AppointmentStatus;
use Modules\LeadAppointment\Infrastructure\Persistence\Eloquent\Models\Appointment;
use Modules\LeadAppointment\Tests\Support\LeadAppointmentFixtures;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    Mail::fake();
    LeadAppointmentFixtures::seedPermissions();
    $this->admin = LeadAppointmentFixtures::superAdmin();
    $this->start = LeadAppointmentFixtures::nextWeekdayAt('10:00');
    LeadAppointmentFixtures::companyRule(1, '08:00', '17:00');
    LeadAppointmentFixtures::companyRule(2, '08:00', '17:00');
    LeadAppointmentFixtures::companyRule(3, '08:00', '17:00');
    LeadAppointmentFixtures::companyRule(4, '08:00', '17:00');
    LeadAppointmentFixtures::companyRule(5, '08:00', '17:00');
});

/**
 * @param array<string, mixed> $overrides
 */
function appointmentPageRecord(CarbonImmutable $start, array $overrides = []): Appointment
{
    return Appointment::factory()->create([
        'lead_id' => LeadAppointmentFixtures::lead()->id,
        'starts_at' => $start,
        'ends_at' => $start->addMinutes(60),
        'timezone' => 'America/Chicago',
        'region' => 'houston',
        ...$overrides,
    ]);
}

it('redirects guests and refuses users without the appointment permission', function (): void {
    $this->get('/appointments')->assertRedirect('/login');

    $this->actingAs(User::factory()->create())
        ->get('/appointments')
        ->assertForbidden();
});

it('renders the appointment list with the filter catalog and capabilities', function (): void {
    $appointment = appointmentPageRecord($this->start);

    $this->actingAs($this->admin)
        ->get('/appointments?'.http_build_query(['sort' => '-starts_at']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Appointments/Index')
            ->has('rows', 1)
            ->where('rows.0.uuid', $appointment->uuid)
            ->where('total', 1)
            ->where('canCreate', true)
            ->where('canUpdate', true)
            ->where('canDelete', true)
            ->where('canExport', true)
            ->has('options.statuses', 8)
            ->has('options.kinds', 5)
            ->has('options.regions', 3)
        );
});

it('renders the create and edit screens', function (): void {
    $appointment = appointmentPageRecord($this->start);

    $this->actingAs($this->admin)
        ->get('/appointments/create')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Appointments/Create')
            ->has('options.kinds', 5)
            ->where('defaults.timezone', 'America/Chicago')
            ->where('defaults.duration_minutes', 420)
        );

    $this->actingAs($this->admin)
        ->get('/appointments/'.$appointment->uuid.'/edit')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Appointments/Edit')
            ->where('appointment.uuid', $appointment->uuid)
        );
});

it('renders the appointment file with its lifecycle capabilities', function (): void {
    $appointment = appointmentPageRecord($this->start);

    $this->actingAs($this->admin)
        ->get('/appointments/'.$appointment->uuid)
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Appointments/Show')
            ->where('appointment.uuid', $appointment->uuid)
            ->where('canUpdate', true)
            ->where('canDelete', true)
            ->where('canRestore', true)
        );
});

it('schedules a visit through the Inertia form and redirects to the file', function (): void {
    $lead = LeadAppointmentFixtures::lead();

    $this->actingAs($this->admin)
        ->post('/appointments', [
            'lead_uuid' => $lead->uuid,
            'kind' => 'inspection',
            'starts_at' => $this->start->format('Y-m-d H:i'),
            'duration_minutes' => 60,
        ])
        ->assertRedirect();

    $appointment = Appointment::query()->firstOrFail();

    expect($appointment->status)->toBe(AppointmentStatus::Scheduled)
        ->and($appointment->lead_id)->toBe($lead->id);
});

it('reschedules a visit through the Inertia form', function (): void {
    $appointment = appointmentPageRecord($this->start);
    $next = $this->start->addDay()->setTime(14, 0);

    $this->actingAs($this->admin)
        ->post('/appointments/'.$appointment->uuid.'/reschedule', [
            'starts_at' => $next->format('Y-m-d H:i'),
            'duration_minutes' => 60,
            'reason' => 'Customer asked for the afternoon',
        ])
        ->assertRedirect(route('appointments.show', $appointment->uuid));

    $appointment->refresh();

    expect($appointment->starts_at->setTimezone('America/Chicago')->format('Y-m-d H:i'))
        ->toBe($next->format('Y-m-d H:i'))
        ->and($appointment->notes)->toContain('Customer asked for the afternoon');
});

it('cancels a visit with a reason and reports an illegal transition', function (): void {
    $appointment = appointmentPageRecord($this->start);

    $this->actingAs($this->admin)
        ->from('/appointments/'.$appointment->uuid)
        ->post('/appointments/'.$appointment->uuid.'/cancel', ['reason' => 'Weather'])
        ->assertRedirect('/appointments/'.$appointment->uuid);

    expect($appointment->refresh()->status)->toBe(AppointmentStatus::Cancelled);

    $completed = appointmentPageRecord($this->start->addDay(), [
        'status' => AppointmentStatus::Completed->value,
    ]);

    $this->actingAs($this->admin)
        ->from('/appointments/'.$completed->uuid)
        ->post('/appointments/'.$completed->uuid.'/cancel', ['reason' => 'Too late'])
        ->assertRedirect('/appointments/'.$completed->uuid)
        ->assertSessionHasErrors('status');
});

it('deletes and restores a visit without changing the lead', function (): void {
    $appointment = appointmentPageRecord($this->start);

    $this->actingAs($this->admin)
        ->delete('/appointments/'.$appointment->uuid)
        ->assertRedirect(route('appointments.index'));

    expect($appointment->refresh()->trashed())->toBeTrue();

    $this->actingAs($this->admin)
        ->from('/appointments')
        ->patch('/appointments/'.$appointment->uuid.'/restore')
        ->assertRedirect('/appointments');

    expect($appointment->refresh()->trashed())->toBeFalse();
});
