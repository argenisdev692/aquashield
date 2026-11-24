<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Modules\LeadAppointment\Infrastructure\Persistence\Eloquent\Models\Lead;
use Modules\LeadAppointment\Tests\Support\LeadAppointmentFixtures;
use Modules\Users\Enums\UserStatus;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    LeadAppointmentFixtures::seedPermissions();
    $this->admin = LeadAppointmentFixtures::superAdmin();
});

it('rejects a guest with 401', function (): void {
    $this->getJson('/api/v1/leads')->assertUnauthorized();
});

it('lists leads for a Sanctum token with VIEW_ANY_LEAD', function (): void {
    Lead::factory()->create(['region' => 'houston', 'state' => 'TX']);

    Sanctum::actingAs($this->admin);

    $this->getJson('/api/v1/leads')
        ->assertOk()
        ->assertJsonPath('total', 1);
});

it('denies a token without the permission with 403', function (): void {
    Sanctum::actingAs(User::factory()->create());

    $this->getJson('/api/v1/leads')->assertForbidden();
});

it('lets a lead-only role read, create and update but not delete', function (): void {
    $leadUser = User::factory()->create();
    $leadUser->givePermissionTo(['VIEW_ANY_LEAD', 'VIEW_LEAD', 'CREATE_LEAD', 'UPDATE_LEAD']);

    $lead = Lead::factory()->create(['status' => 'new', 'region' => 'houston', 'state' => 'TX']);

    Sanctum::actingAs($leadUser);

    $this->getJson('/api/v1/leads/'.$lead->uuid)
        ->assertOk()
        ->assertJsonPath('uuid', $lead->uuid);

    $this->postJson('/api/v1/leads', [
        'first_name' => 'Ada',
        'last_name' => 'Lovelace',
        'email' => 'ada@aquashieldrestorationusa.com',
        'region' => 'houston',
    ])->assertCreated();

    $this->putJson('/api/v1/leads/'.$lead->uuid, [
        'first_name' => 'Ada',
        'last_name' => 'Lovelace',
        'email' => 'ada@aquashieldrestorationusa.com',
        'region' => 'houston',
    ])->assertOk();

    $this->deleteJson('/api/v1/leads/'.$lead->uuid)->assertForbidden();
});

it('exposes appointment management through the Sanctum surface', function (): void {
    $lead = LeadAppointmentFixtures::lead();
    $date = LeadAppointmentFixtures::nextWeekdayAt('10:00');

    LeadAppointmentFixtures::companyRule(1, '08:00', '17:00');

    Sanctum::actingAs($this->admin);

    $this->getJson('/api/v1/appointments')->assertOk();

    $this->postJson('/api/v1/appointments', [
        'lead_uuid' => $lead->uuid,
        'kind' => 'inspection',
        'starts_at' => $date->format('Y-m-d H:i'),
        'duration_minutes' => 60,
    ])->assertCreated()->assertJsonPath('status', 'scheduled');
});

it('denies a suspended token with 403 on the admin API', function (): void {
    $suspended = LeadAppointmentFixtures::superAdmin();
    $suspended->forceFill([
        'status' => UserStatus::Suspended,
        'suspended_at' => now(),
    ])->save();

    Sanctum::actingAs($suspended);

    $this->getJson('/api/v1/leads')->assertForbidden();
});

it('denies a token that still has to replace its temporary password', function (): void {
    $this->admin->forceFill(['must_change_password' => true])->save();

    Sanctum::actingAs($this->admin);

    $this->getJson('/api/v1/leads')->assertForbidden();
});
