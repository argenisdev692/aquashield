<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\LeadAppointment\Domain\Enums\AppointmentStatus;
use Modules\LeadAppointment\Infrastructure\Persistence\Eloquent\Models\Appointment;
use Modules\LeadAppointment\Infrastructure\Persistence\Eloquent\Models\Lead;
use Modules\LeadAppointment\Tests\Support\LeadAppointmentFixtures;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    LeadAppointmentFixtures::seedPermissions();
    $this->admin = LeadAppointmentFixtures::superAdmin();
});

it('records the inspection date when the operator marks the lead as called', function (): void {
    $lead = Lead::factory()->create(['status' => 'new', 'region' => 'houston', 'state' => 'TX']);

    $this->actingAs($this->admin)
        ->postJson('/leads/'.$lead->uuid.'/status', [
            'status_lead' => 'Called',
            'inspection_date' => '2026-12-01',
            'inspection_time' => '10:00',
            'inspection_status' => 'Pending',
            'note' => 'Customer confirmed the inspection date.',
        ])
        ->assertOk()
        ->assertJsonPath('status', 'contacted')
        ->assertJsonPath('inspection_date', '2026-12-01')
        ->assertJsonPath('inspection_time', '10:00')
        ->assertJsonPath('inspection_status', 'pending');

    $lead->refresh();

    expect($lead->status->value)->toBe('contacted')
        ->and($lead->inspection_date?->toDateString())->toBe('2026-12-01')
        ->and($lead->inspection_status?->value)->toBe('pending');
});

it('updates the inspection date without requiring a second status transition', function (): void {
    $lead = Lead::factory()->create(['status' => 'contacted', 'region' => 'houston', 'state' => 'TX']);

    $this->actingAs($this->admin)
        ->postJson('/leads/'.$lead->uuid.'/status', [
            'status' => 'contacted',
            'inspection_date' => '2026-12-02',
            'inspection_time' => '14:30',
        ])
        ->assertOk()
        ->assertJsonPath('status', 'contacted')
        ->assertJsonPath('inspection_date', '2026-12-02')
        ->assertJsonPath('inspection_time', '14:30');
});

it('keeps the confirmed appointment as the inspection date when one exists', function (): void {
    $lead = Lead::factory()->create([
        'status' => 'contacted',
        'region' => 'houston',
        'state' => 'TX',
        'inspection_date' => '2026-12-01',
        'inspection_time' => '10:00',
        'inspection_status' => 'pending',
    ]);

    Appointment::factory()->create([
        'lead_id' => $lead->id,
        'status' => AppointmentStatus::Confirmed->value,
        'starts_at' => '2026-12-03 16:00:00',
        'ends_at' => '2026-12-03 17:00:00',
        'timezone' => 'America/Chicago',
    ]);

    $this->actingAs($this->admin)
        ->getJson('/leads/'.$lead->uuid)
        ->assertOk()
        ->assertJsonPath('inspection_date', '2026-12-03')
        ->assertJsonPath('inspection_time', '10:00')
        ->assertJsonPath('inspection_status', 'confirmed');
});

it('rejects the retired estimate_sent status', function (): void {
    $lead = Lead::factory()->create(['status' => 'new', 'region' => 'houston', 'state' => 'TX']);

    $this->actingAs($this->admin)
        ->postJson('/leads/'.$lead->uuid.'/status', ['status' => 'estimate_sent'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['status']);
});

it('maps the legacy pending and declined labels to the current pipeline', function (): void {
    $pending = Lead::factory()->create(['status' => 'new', 'region' => 'houston', 'state' => 'TX']);
    $declined = Lead::factory()->create(['status' => 'new', 'region' => 'houston', 'state' => 'TX']);

    $this->actingAs($this->admin)
        ->postJson('/leads/'.$pending->uuid.'/status', ['status_lead' => 'Pending'])
        ->assertOk()
        ->assertJsonPath('status', 'nurture');

    $this->actingAs($this->admin)
        ->postJson('/leads/'.$declined->uuid.'/status', ['status_lead' => 'Declined'])
        ->assertOk()
        ->assertJsonPath('status', 'lost');
});
