<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\LeadAppointment\Tests\Support\LeadAppointmentFixtures;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    LeadAppointmentFixtures::seedPermissions();
    $this->admin = LeadAppointmentFixtures::superAdmin();
});

it('creates, updates and lists technicians by region', function (): void {
    $created = $this->actingAs($this->admin)->postJson('/technicians', [
        'name' => 'Houston Crew A',
        'region' => 'houston',
        'daily_capacity' => 4,
        'travel_buffer_minutes' => 30,
    ])->assertCreated()->assertJsonPath('name', 'Houston Crew A');

    $uuid = $created->json('uuid');

    $this->actingAs($this->admin)
        ->putJson('/technicians/'.$uuid, [
            'name' => 'Houston Crew B',
            'region' => 'houston',
            'daily_capacity' => 6,
            'travel_buffer_minutes' => 20,
        ])
        ->assertOk()
        ->assertJsonPath('name', 'Houston Crew B')
        ->assertJsonPath('daily_capacity', 6);

    $this->actingAs($this->admin)
        ->getJson('/technicians?region=houston')
        ->assertOk()
        ->assertJsonPath('data.0.uuid', $uuid);
});

it('rejects invalid catalog input with 422', function (): void {
    $this->actingAs($this->admin)
        ->postJson('/technicians', [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name', 'region', 'daily_capacity', 'travel_buffer_minutes']);
});

it('answers 401 and 403 for the catalog', function (): void {
    $this->getJson('/technicians')->assertUnauthorized();

    $this->actingAs(User::factory()->create())
        ->getJson('/technicians')
        ->assertForbidden();
});
