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

it('accepts the legacy column aliases and stores the qualification fields', function (): void {
    $response = $this->actingAs($this->admin)->postJson('/leads', [
        'first_name' => 'Ada',
        'last_name' => 'Lovelace',
        'email' => 'ada@example.com',
        'zipcode' => '77002',
        'region' => 'houston',
        'damage_detail' => 'Hail damage on the south slope.',
        'insurance_property' => true,
        'intent_to_claim' => true,
        'message' => 'Please call in the morning.',
        'follow_up_date' => '2026-12-01',
        'additional_note' => 'Gate code 4412.',
        'latitude' => 29.7604,
        'longitude' => -95.3698,
    ])->assertCreated();

    $uuid = $response->json('uuid');

    $this->assertDatabaseHas('leads', [
        'uuid' => $uuid,
        'zip_code' => '77002',
        'damage_description' => 'Hail damage on the south slope.',
        'insurance_property' => true,
        'intent_to_claim' => true,
        'message' => 'Please call in the morning.',
        'additional_note' => 'Gate code 4412.',
    ]);

    $this->actingAs($this->admin)
        ->getJson('/leads/'.$uuid)
        ->assertOk()
        ->assertJsonPath('zip_code', '77002')
        ->assertJsonPath('insurance_property', true)
        ->assertJsonPath('intent_to_claim', true)
        ->assertJsonPath('follow_up_date', '2026-12-01')
        ->assertJsonPath('latitude', 29.7604)
        ->assertJsonPath('longitude', -95.3698);
});

it('updates the qualification fields without dropping the existing contract', function (): void {
    $lead = LeadAppointmentFixtures::lead();

    $this->actingAs($this->admin)
        ->putJson('/leads/'.$lead->uuid, [
            'first_name' => 'Ada',
            'last_name' => 'Lovelace',
            'email' => 'ada@example.com',
            'region' => 'houston',
            'insurance_property' => true,
            'intent_to_claim' => false,
            'follow_up_date' => '2026-12-15',
            'additional_note' => 'Customer prefers SMS.',
        ])
        ->assertOk()
        ->assertJsonPath('insurance_property', true)
        ->assertJsonPath('intent_to_claim', false)
        ->assertJsonPath('follow_up_date', '2026-12-15')
        ->assertJsonPath('additional_note', 'Customer prefers SMS.');
});

it('derives inspection date, time and status from the latest appointment', function (): void {
    $lead = LeadAppointmentFixtures::lead();

    Appointment::factory()->create([
        'lead_id' => $lead->id,
        'status' => AppointmentStatus::Confirmed->value,
        'starts_at' => '2026-12-01 16:00:00',
        'ends_at' => '2026-12-01 17:00:00',
        'timezone' => 'America/Chicago',
    ]);

    $this->actingAs($this->admin)
        ->getJson('/leads/'.$lead->uuid)
        ->assertOk()
        ->assertJsonPath('inspection_date', '2026-12-01')
        ->assertJsonPath('inspection_time', '10:00')
        ->assertJsonPath('inspection_status', 'confirmed');
});

it('accepts the qualification fields on the public form', function (): void {
    $response = $this->postJson('/api/leads', [
        'first_name' => 'Ada',
        'last_name' => 'Lovelace',
        'email' => 'ada@aquashieldrestorationusa.com',
        'region' => 'houston',
        'insurance_property' => true,
        'intent_to_claim' => false,
        'message' => 'Roof leak in the garage.',
    ])->assertCreated();

    $lead = Lead::query()->where('uuid', $response->json('reference'))->firstOrFail();

    expect($lead->insurance_property)->toBeTrue()
        ->and($lead->intent_to_claim)->toBeFalse()
        ->and($lead->message)->toBe('Roof leak in the garage.');
});
