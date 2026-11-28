<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\LeadAppointment\Domain\Enums\AppointmentStatus;
use Modules\LeadAppointment\Infrastructure\Persistence\Eloquent\Models\Appointment;
use Modules\LeadAppointment\Tests\Support\LeadAppointmentFixtures;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    LeadAppointmentFixtures::seedPermissions();
    $this->admin = LeadAppointmentFixtures::superAdmin();
    $this->start = LeadAppointmentFixtures::nextWeekdayAt('10:00');
});

/**
 * @param array<string, mixed> $overrides
 */
function calendarEventRecord(array $overrides = []): Appointment
{
    $start = test()->start;

    return Appointment::factory()->create([
        'lead_id' => LeadAppointmentFixtures::lead()->id,
        'starts_at' => $start,
        'ends_at' => $start->addHour(),
        'timezone' => 'America/Chicago',
        'region' => 'houston',
        'status' => AppointmentStatus::Scheduled,
        ...$overrides,
    ])->load('lead');
}

it('returns the slim event projection for the visible window', function (): void {
    $appointment = calendarEventRecord();

    $this->actingAs($this->admin)
        ->getJson('/appointments/calendar-events?'.http_build_query([
            'from' => $this->start->toDateString(),
            'to' => $this->start->toDateString(),
        ]))
        ->assertOk()
        ->assertJsonPath('data.0.uuid', $appointment->uuid)
        ->assertJsonPath('data.0.customer_name', trim($appointment->lead->first_name.' '.$appointment->lead->last_name))
        ->assertJsonPath('data.0.status', AppointmentStatus::Scheduled->value)
        ->assertJsonPath('data.0.timezone', 'America/Chicago')
        ->assertJsonMissingPath('data.0.notes')
        ->assertJsonMissingPath('data.0.customer_phone');
});

it('keeps cancelled visits in the feed with their state', function (): void {
    $appointment = calendarEventRecord(['status' => AppointmentStatus::Cancelled]);

    $this->actingAs($this->admin)
        ->getJson('/appointments/calendar-events?'.http_build_query([
            'from' => $this->start->toDateString(),
            'to' => $this->start->toDateString(),
        ]))
        ->assertOk()
        ->assertJsonPath('data.0.uuid', $appointment->uuid)
        ->assertJsonPath('data.0.status', AppointmentStatus::Cancelled->value);
});

it('rejects a window over the configured maximum', function (): void {
    $this->actingAs($this->admin)
        ->getJson('/appointments/calendar-events?from=2026-01-01&to=2026-12-31')
        ->assertUnprocessable();
});

it('applies the configured maximum as inclusive calendar days', function (): void {
    config(['lead-appointment.calendar_max_days' => 2]);

    $this->actingAs($this->admin)
        ->getJson('/appointments/calendar-events?from=2026-01-01&to=2026-01-02')
        ->assertOk();

    $this->actingAs($this->admin)
        ->getJson('/appointments/calendar-events?from=2026-01-01&to=2026-01-03')
        ->assertUnprocessable();
});

it('scopes the feed to the dashboard region filter', function (): void {
    calendarEventRecord(['region' => 'houston']);
    $dallas = calendarEventRecord(['region' => 'dallas']);

    $this->actingAs($this->admin)
        ->getJson('/appointments/calendar-events?'.http_build_query([
            'from' => $this->start->toDateString(),
            'to' => $this->start->toDateString(),
            'region' => 'dallas',
        ]))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.uuid', $dallas->uuid);
});

it('refuses an unknown region', function (): void {
    $this->actingAs($this->admin)
        ->getJson('/appointments/calendar-events?'.http_build_query([
            'from' => $this->start->toDateString(),
            'to' => $this->start->toDateString(),
            'region' => 'mars',
        ]))
        ->assertUnprocessable();
});

it('refuses the feed without the appointment permission', function (): void {
    $this->actingAs(User::factory()->create())
        ->getJson('/appointments/calendar-events?from=2026-11-01&to=2026-11-30')
        ->assertForbidden();
});
