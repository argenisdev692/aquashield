<?php

declare(strict_types=1);

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Modules\LeadAppointment\Domain\Enums\AppointmentKind;
use Modules\LeadAppointment\Domain\Enums\LeadSource;
use Modules\LeadAppointment\Domain\Enums\LeadStatus;
use Modules\LeadAppointment\Domain\Enums\Region;
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

it('zeroes the gated period counters for a user without permission', function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-11-15 10:00:00', 'America/Chicago'));

    $lead = LeadAppointmentFixtures::lead();
    $start = CarbonImmutable::parse('2026-11-10 10:00:00', 'America/Chicago');

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
            ->has('stats.leads.series', 0)
            ->where('stats.leads.current', 0)
            ->where('stats.leads.previous', 0)
            ->where('stats.inspections.current', 0)
            ->where('stats.won.current', 0)
            ->where('stats.appointments.current', 0)
            ->where('canViewLeads', false)
            ->where('canViewCalendar', false)
            ->where('canViewAppointment', false)
            ->where('canViewLead', false)
        );
});

it('renders the real lead columns and the default last-30-days KPI snapshot', function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-11-15 10:00:00', 'America/Chicago'));

    $lead = LeadAppointmentFixtures::lead();
    $start = CarbonImmutable::parse('2026-11-10 10:00:00', 'America/Chicago');

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
            ->where('range', 'last_30_days')
            ->where('rangeFrom', '2026-10-17')
            ->where('rangeTo', '2026-11-15')
            ->has('rangeOptions', 3)
            ->where('stats.leads.current', 1)
            ->where('stats.leads.previous', 0)
            ->has('stats.leads.series', 30)
            ->where('stats.leads.series.29', 1)
            ->where('stats.inspections.current', 1)
            ->where('stats.inspections.previous', 0)
            ->has('stats.inspections.series', 30)
            ->where('stats.inspections.series.24', 1)
            ->where('stats.won.current', 0)
            ->where('stats.won.previous', 0)
            ->where('stats.appointments.current', 1)
            ->where('stats.appointments.previous', 0)
            ->where('canCreate', true)
            ->where('canUpdate', true)
            ->where('canViewLeads', true)
            ->where('canScheduleAppointment', true)
            ->where('canViewCalendar', true)
            ->where('canViewAppointment', true)
            ->where('canViewLead', true)
            ->has('options.kinds', 5)
            ->has('options.regions', 3)
            ->has('options.statuses', 7)
            ->has('options.sources', 7)
            ->has('leadOptions.regions', 3)
            ->has('leadOptions.owners', 1)
            ->has('googleMapsApiKey')
            ->has('defaultDuration')
            ->where('defaultTimezone', 'America/Chicago')
        );
});

it('compares every KPI with the previous calendar month for this_month', function (): void {
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
        ->get('/dashboard?range=this_month')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->where('range', 'this_month')
            ->where('rangeFrom', '2026-11-01')
            ->where('rangeTo', '2026-11-30')
            ->where('stats.leads.current', 1)
            ->where('stats.leads.previous', 1)
            ->has('stats.leads.series', 30)
            ->where('stats.leads.series.14', 1)
            ->where('stats.inspections.current', 1)
            ->where('stats.inspections.previous', 1)
            ->has('stats.inspections.series', 30)
            ->where('stats.inspections.series.19', 1)
            ->where('stats.won.current', 1)
            ->where('stats.won.previous', 0)
            ->has('stats.won.series', 30)
            ->where('stats.won.series.4', 1)
            ->where('stats.appointments.current', 2)
            ->where('stats.appointments.previous', 2)
            ->has('stats.appointments.series', 30)
            ->where('stats.appointments.series.19', 1)
            ->where('stats.appointments.series.20', 1)
        );
});

it('reads the previous month with the month before it as the comparison', function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-11-15 10:00:00', 'America/Chicago'));

    $currentLead = LeadAppointmentFixtures::lead();
    $previousLead = LeadAppointmentFixtures::lead();

    Lead::query()->whereKey($currentLead->id)->update([
        'created_at' => CarbonImmutable::parse('2026-11-10 09:00:00', 'America/Chicago')->utc(),
    ]);

    Lead::query()->whereKey($previousLead->id)->update([
        'created_at' => CarbonImmutable::parse('2026-10-10 09:00:00', 'America/Chicago')->utc(),
    ]);

    $start = CarbonImmutable::parse('2026-10-20 10:00:00', 'America/Chicago');

    Appointment::factory()->create([
        'lead_id' => $previousLead->id,
        'kind' => AppointmentKind::Inspection->value,
        'starts_at' => $start->utc(),
        'ends_at' => $start->addHour()->utc(),
        'timezone' => 'America/Chicago',
        'region' => 'houston',
    ]);

    $this->actingAs($this->admin)
        ->get('/dashboard?range=previous_month')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->where('range', 'previous_month')
            ->where('rangeFrom', '2026-10-01')
            ->where('rangeTo', '2026-10-31')
            ->where('columns.0.total', 1)
            ->where('stats.leads.current', 1)
            ->where('stats.leads.previous', 0)
            ->has('stats.leads.series', 31)
            ->where('stats.leads.series.9', 1)
            ->where('stats.inspections.current', 1)
            ->where('stats.inspections.previous', 0)
            ->has('stats.inspections.series', 31)
            ->where('stats.inspections.series.19', 1)
            ->where('stats.appointments.current', 1)
            ->where('stats.appointments.previous', 0)
        );
});

it('scopes the board, the KPIs and the appointment counters by region and source', function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-11-15 10:00:00', 'America/Chicago'));

    $houstonLead = LeadAppointmentFixtures::lead();
    $dallasLead = LeadAppointmentFixtures::lead(Region::Dallas);

    Lead::query()->whereKey($dallasLead->id)->update([
        'source' => LeadSource::Meta->value,
    ]);

    $book = function (Lead $lead, string $startsAt, string $region): void {
        $start = CarbonImmutable::parse($startsAt, 'America/Chicago');

        Appointment::factory()->create([
            'lead_id' => $lead->id,
            'kind' => AppointmentKind::Inspection->value,
            'starts_at' => $start->utc(),
            'ends_at' => $start->addHour()->utc(),
            'timezone' => 'America/Chicago',
            'region' => $region,
        ]);
    };

    $book($houstonLead, '2026-11-10 10:00:00', 'houston');
    $book($dallasLead, '2026-11-12 10:00:00', 'dallas');

    $this->actingAs($this->admin)
        ->get('/dashboard?filter[region]=dallas&filter[source]=meta')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->where('columns.0.total', 1)
            ->where('stats.leads.current', 1)
            ->where('stats.leads.previous', 0)
            ->where('stats.inspections.current', 1)
            ->where('stats.appointments.current', 1)
        );

    $this->actingAs($this->admin)
        ->get('/dashboard?filter[region]=houston')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->where('columns.0.total', 1)
            ->where('stats.leads.current', 1)
            ->where('stats.inspections.current', 1)
            ->where('stats.appointments.current', 1)
        );
});

it('falls back to the default period for an unknown or malformed range', function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-11-15 10:00:00', 'America/Chicago'));

    LeadAppointmentFixtures::lead();

    $this->actingAs($this->admin)
        ->get('/dashboard?range=last_90_days&filter[region]=mars')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->where('range', 'last_30_days')
            ->where('stats.leads.current', 1)
        );

    $this->actingAs($this->admin)
        ->get('/dashboard?range[]=this_month')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->where('range', 'last_30_days')
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
