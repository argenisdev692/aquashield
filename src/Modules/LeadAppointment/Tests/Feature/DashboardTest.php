<?php

declare(strict_types=1);

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Modules\LeadAppointment\Domain\Enums\AppointmentKind;
use Modules\LeadAppointment\Domain\Enums\LeadStatus;
use Modules\LeadAppointment\Infrastructure\Persistence\Eloquent\Models\Appointment;
use Modules\LeadAppointment\Infrastructure\Persistence\Eloquent\Models\Lead;
use Modules\LeadAppointment\Tests\Support\LeadAppointmentFixtures;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    LeadAppointmentFixtures::seedPermissions();
    $this->admin = LeadAppointmentFixtures::superAdmin();
});

it('redirects guests to login', function (): void {
    $this->get('/dashboard')->assertRedirect('/login');
});

it('zeroes the gated monthly counters for a user without permission', function (): void {
    $lead = LeadAppointmentFixtures::lead();
    $start = CarbonImmutable::now('America/Chicago')->startOfMonth()->addDay()->setTime(10, 0);

    Appointment::factory()->create([
        'lead_id' => $lead->id,
        'starts_at' => $start,
        'ends_at' => $start->addHours(7),
        'timezone' => 'America/Chicago',
        'region' => 'houston',
    ]);

    $this->actingAs(User::factory()->create())
        ->get('/dashboard')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->has('columns', 0)
            ->has('appointments', 0)
            ->where('stats.leads.current', 0)
            ->where('stats.leads.previous', 0)
            ->where('stats.inspections.current', 0)
            ->where('stats.won.current', 0)
            ->where('stats.appointments.current', 0)
        );
});

it('renders the real lead columns and the current month of appointments', function (): void {
    $lead = LeadAppointmentFixtures::lead();
    $start = CarbonImmutable::now('America/Chicago')->startOfMonth()->addDay()->setTime(10, 0);

    Appointment::factory()->create([
        'lead_id' => $lead->id,
        'starts_at' => $start,
        'ends_at' => $start->addHours(7),
        'timezone' => 'America/Chicago',
        'region' => 'houston',
    ]);

    $this->actingAs($this->admin)
        ->get('/dashboard')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->has('columns', 7)
            ->where('columns.0.status', 'new')
            ->where('columns.0.total', 1)
            ->has('appointments', 1)
            ->where('appointments.0.lead_uuid', $lead->uuid)
            ->where('stats.leads.current', 1)
            ->where('stats.leads.previous', 0)
            ->where('stats.inspections.current', 1)
            ->where('stats.inspections.previous', 0)
            ->where('stats.won.current', 0)
            ->where('stats.won.previous', 0)
            ->where('stats.appointments.current', 1)
            ->where('stats.appointments.previous', 0)
            ->where('canCreate', true)
            ->where('canUpdate', true)
            ->where('canScheduleAppointment', true)
            ->has('options.kinds', 5)
            ->has('options.regions', 3)
            ->has('leadOptions.regions', 3)
            ->has('leadOptions.owners', 1)
            ->has('googleMapsApiKey')
            ->has('defaultDuration')
            ->where('defaultTimezone', 'America/Chicago')
        );
});

it('compares every KPI with the previous calendar month', function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-11-15 10:00:00', 'America/Chicago'));

    $currentLead = LeadAppointmentFixtures::lead();
    $previousLead = LeadAppointmentFixtures::lead();

    Lead::query()->whereKey($previousLead->id)->update([
        'created_at' => CarbonImmutable::parse('2026-10-10 09:00:00', 'America/Chicago')->utc(),
    ]);

    Lead::query()->whereKey($currentLead->id)->update([
        'status' => LeadStatus::Won->value,
        'converted_at' => CarbonImmutable::parse('2026-11-05 12:00:00', 'America/Chicago')->utc(),
    ]);

    $book = function (Lead $lead, string $startsAt, AppointmentKind $kind): void {
        $start = CarbonImmutable::parse($startsAt, 'America/Chicago');

        Appointment::factory()->create([
            'lead_id' => $lead->id,
            'kind' => $kind->value,
            'starts_at' => $start->utc(),
            'ends_at' => $start->addHour()->utc(),
            'timezone' => 'America/Chicago',
            'region' => 'houston',
        ]);
    };

    $book($currentLead, '2026-11-20 10:00:00', AppointmentKind::Inspection);
    $book($previousLead, '2026-10-20 10:00:00', AppointmentKind::Inspection);
    $book($currentLead, '2026-11-21 10:00:00', AppointmentKind::Estimate);
    $book($previousLead, '2026-10-22 10:00:00', AppointmentKind::Estimate);

    $this->actingAs($this->admin)
        ->get('/dashboard')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->where('stats.leads.current', 1)
            ->where('stats.leads.previous', 1)
            ->where('stats.inspections.current', 1)
            ->where('stats.inspections.previous', 1)
            ->where('stats.won.current', 1)
            ->where('stats.won.previous', 0)
            ->where('stats.appointments.current', 2)
            ->where('stats.appointments.previous', 2)
        );
});

it('exposes the shared transition map', function (): void {
    $this->actingAs($this->admin)
        ->get('/dashboard')
        ->assertInertia(fn (Assert $page) => $page
            ->has('transitions.new', 5)
            ->where('transitions.new.0', 'contacted')
        );
});
