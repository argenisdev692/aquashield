<?php

declare(strict_types=1);

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\LeadAppointment\Domain\Enums\Region;
use Modules\LeadAppointment\Infrastructure\Persistence\Eloquent\Models\Lead;
use Modules\LeadAppointment\Tests\Support\LeadAppointmentFixtures;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    LeadAppointmentFixtures::seedPermissions();
    $this->admin = LeadAppointmentFixtures::superAdmin();
});

it('answers 401 to a guest JSON request', function (): void {
    $this->getJson('/leads')->assertUnauthorized();
    $this->postJson('/leads', [])->assertUnauthorized();
});

it('forbids a user without VIEW_ANY_LEAD', function (): void {
    $this->actingAs(User::factory()->create())->getJson('/leads')->assertForbidden();
});

it('forbids lead reads and writes without the matching permission', function (): void {
    $lead = Lead::factory()->create();
    $outsider = User::factory()->create();

    $this->actingAs($outsider)->getJson('/leads/'.$lead->uuid)->assertForbidden();
    $this->actingAs($outsider)->putJson('/leads/'.$lead->uuid, [])->assertForbidden();
    $this->actingAs($outsider)->deleteJson('/leads/'.$lead->uuid)->assertForbidden();
    $this->actingAs($outsider)->postJson('/leads/'.$lead->uuid.'/status', [])->assertForbidden();
    $this->actingAs($outsider)->postJson('/leads/'.$lead->uuid.'/activities', [])->assertForbidden();
    $this->actingAs($outsider)->postJson('/leads/'.$lead->uuid.'/merge', [])->assertForbidden();
    $this->actingAs($outsider)->patchJson('/leads/'.$lead->uuid.'/restore')->assertForbidden();
    $this->actingAs($outsider)->postJson('/leads/bulk-delete', ['uuids' => [$lead->uuid]])->assertForbidden();
    $this->actingAs($outsider)->postJson('/leads/bulk-restore', ['uuids' => [$lead->uuid]])->assertForbidden();
});

it('rejects invalid create input with 422', function (): void {
    $this->actingAs($this->admin)
        ->postJson('/leads', [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['first_name', 'last_name', 'email', 'phone']);
});

it('creates, lists and shows a lead without exposing the internal id', function (): void {
    $created = $this->actingAs($this->admin)->postJson('/leads', [
        'first_name' => 'Ada',
        'last_name' => 'Lovelace',
        'email' => 'ada@example.com',
        'phone' => '(713) 555-0199',
        'address' => '123 Main St',
        'city' => 'Houston',
        'state' => 'TX',
        'zip_code' => '77002',
        'region' => 'houston',
    ])->assertCreated();

    $uuid = $created->json('uuid');

    $this->actingAs($this->admin)
        ->getJson('/leads?search=Ada')
        ->assertOk()
        ->assertJsonPath('total', 1)
        ->assertJsonPath('rows.0.uuid', $uuid)
        ->assertJsonMissingPath('rows.0.id');

    $this->actingAs($this->admin)
        ->getJson('/leads/'.$uuid)
        ->assertOk()
        ->assertJsonPath('uuid', $uuid)
        ->assertJsonPath('full_name', 'Ada Lovelace')
        ->assertJsonMissingPath('id');

    $this->assertDatabaseHas('leads', [
        'uuid' => $uuid,
        'created_by' => $this->admin->id,
    ]);
});

it('title-cases lead names and rejects digits or more than 50 characters', function (): void {
    $created = $this->actingAs($this->admin)->postJson('/leads', [
        'first_name' => '  pedro pablo ',
        'last_name' => ' ramirez gomez ',
        'email' => 'pedro@example.com',
        'region' => 'houston',
    ])->assertCreated();

    $this->assertDatabaseHas('leads', [
        'uuid' => $created->json('uuid'),
        'first_name' => 'Pedro Pablo',
        'last_name' => 'Ramirez Gomez',
    ]);

    $this->actingAs($this->admin)->postJson('/leads', [
        'first_name' => 'Pedro123',
        'last_name' => 'Ramirez',
        'email' => 'pedro123@example.com',
        'region' => 'houston',
    ])->assertUnprocessable()->assertJsonValidationErrors(['first_name']);

    $this->actingAs($this->admin)->postJson('/leads', [
        'first_name' => str_repeat('a', 51),
        'last_name' => 'Ramirez',
        'email' => 'long-name@example.com',
        'region' => 'houston',
    ])->assertUnprocessable()->assertJsonValidationErrors(['first_name']);
});

it('derives the lead region from the address and ignores a contradictory region', function (): void {
    $florida = $this->actingAs($this->admin)->postJson('/leads', [
        'first_name' => 'Mia',
        'last_name' => 'Owner',
        'email' => 'mia@example.com',
        'state' => 'FL',
        'city' => 'Miami',
        'zip_code' => '33101',
        'region' => 'houston',
    ])->assertCreated();

    $this->assertDatabaseHas('leads', [
        'uuid' => $florida->json('uuid'),
        'region' => 'florida',
    ]);

    $dallas = $this->actingAs($this->admin)->postJson('/leads', [
        'first_name' => 'Dallas',
        'last_name' => 'Owner',
        'email' => 'dallas@example.com',
        'state' => 'TX',
        'city' => 'Plano',
        'zip_code' => '75024',
        'region' => 'houston',
    ])->assertCreated();

    $this->assertDatabaseHas('leads', [
        'uuid' => $dallas->json('uuid'),
        'region' => 'dallas',
    ]);

    $lead = Lead::factory()->create([
        'region' => 'houston',
        'state' => 'TX',
        'city' => 'Houston',
        'zip_code' => '77002',
    ]);

    $this->actingAs($this->admin)->putJson('/leads/'.$lead->uuid, [
        'first_name' => 'Mia',
        'last_name' => 'Owner',
        'email' => 'mia@example.com',
        'state' => 'FL',
        'city' => 'Miami',
        'zip_code' => '33101',
        'region' => 'houston',
    ])->assertOk();

    expect($lead->refresh()->region)->toBe(Region::Florida);

    $this->actingAs($this->admin)->putJson('/leads/'.$lead->uuid, [
        'first_name' => 'Mia',
        'last_name' => 'Owner',
        'email' => 'mia@example.com',
        'state' => 'TX',
        'city' => 'Austin',
        'zip_code' => '78701',
        'region' => 'houston',
    ])->assertOk();

    expect($lead->refresh()->region)->toBeNull();
});

it('enforces the lead status transition map', function (): void {
    $lead = Lead::factory()->create(['status' => 'new', 'region' => 'houston', 'state' => 'TX']);

    $this->actingAs($this->admin)
        ->postJson('/leads/'.$lead->uuid.'/status', ['status' => 'contacted', 'note' => 'Called the customer.'])
        ->assertOk()
        ->assertJsonPath('status', 'contacted');

    $this->actingAs($this->admin)
        ->postJson('/leads/'.$lead->uuid.'/status', ['status' => 'new'])
        ->assertUnprocessable();
});

it('logs a contact activity and updates the last contact timestamp', function (): void {
    $lead = Lead::factory()->create(['region' => 'houston', 'state' => 'TX']);

    $this->actingAs($this->admin)
        ->postJson('/leads/'.$lead->uuid.'/activities', [
            'type' => 'call',
            'outcome' => 'connected',
            'notes' => 'Customer wants a morning inspection.',
        ])
        ->assertCreated()
        ->assertJsonPath('type', 'call');

    expect($lead->refresh()->last_contacted_at)->not->toBeNull();
});

it('merges a duplicate into the target lead and soft-deletes it', function (): void {
    $target = Lead::factory()->create(['region' => 'houston', 'state' => 'TX']);
    $duplicate = Lead::factory()->create(['region' => 'houston', 'state' => 'TX']);

    $this->actingAs($this->admin)
        ->postJson('/leads/'.$target->uuid.'/merge', ['duplicate_uuid' => $duplicate->uuid])
        ->assertOk()
        ->assertJsonPath('uuid', $target->uuid);

    expect($duplicate->refresh()->deleted_at)->not->toBeNull()
        ->and($duplicate->duplicate_of_id)->toBe($target->id);
});

it('rejects a self-merge and a trashed duplicate with 422', function (): void {
    $target = Lead::factory()->create(['region' => 'houston', 'state' => 'TX']);
    $trashed = Lead::factory()->create(['region' => 'houston', 'state' => 'TX']);
    $trashed->delete();

    $this->actingAs($this->admin)
        ->postJson('/leads/'.$target->uuid.'/merge', ['duplicate_uuid' => $target->uuid])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['duplicate_uuid']);

    $this->actingAs($this->admin)
        ->postJson('/leads/'.$target->uuid.'/merge', ['duplicate_uuid' => $trashed->uuid])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['duplicate_uuid']);
});

it('exports the filtered list as CSV and PDF', function (): void {
    Lead::factory()->create([
        'first_name' => 'Ada',
        'last_name' => 'Lovelace',
        'email' => 'ada@example.com',
        'region' => 'houston',
        'state' => 'TX',
    ]);
    Lead::factory()->create([
        'first_name' => 'Bob',
        'last_name' => 'Barker',
        'email' => 'bob@example.com',
        'region' => 'houston',
        'state' => 'TX',
    ]);

    $csv = $this->actingAs($this->admin)
        ->get('/leads/export/csv?search=Ada')
        ->assertOk()
        ->streamedContent();

    expect($csv)
        ->toContain('Name')
        ->toContain('Ada Lovelace')
        ->not->toContain('Bob Barker');

    $pdf = $this->actingAs($this->admin)
        ->get('/leads/export/pdf?search=Ada')
        ->assertOk();

    expect($pdf->getContent())->toStartWith('%PDF');
});

it('forbids an export without EXPORT_LEAD', function (): void {
    $this->actingAs(User::factory()->create())
        ->get('/leads/export/csv')
        ->assertForbidden();
});

it('filters by the business date rather than the UTC date', function (): void {
    $lead = Lead::factory()->create();
    $lead->forceFill(['created_at' => CarbonImmutable::parse('2026-11-25 05:30:00', 'UTC')])->saveQuietly();

    $this->actingAs($this->admin)
        ->getJson('/leads?'.http_build_query([
            'filter' => ['date_from' => '2026-11-24', 'date_to' => '2026-11-24'],
        ]))
        ->assertOk()
        ->assertJsonPath('total', 1);
});

it('exposes the inverse user relations', function (): void {
    Lead::factory()->create([
        'owner_id' => $this->admin->id,
        'created_by' => $this->admin->id,
    ]);

    expect($this->admin->ownedLeads()->count())->toBe(1)
        ->and($this->admin->createdLeads()->count())->toBe(1);
});

it('audits single and bulk lead deletes', function (): void {
    $first = Lead::factory()->create();
    $second = Lead::factory()->create();

    $this->actingAs($this->admin)
        ->deleteJson('/leads/'.$first->uuid)
        ->assertNoContent();

    $this->actingAs($this->admin)
        ->postJson('/leads/bulk-delete', ['uuids' => [$second->uuid]])
        ->assertNoContent();

    $events = Activity::query()
        ->where('subject_type', Lead::class)
        ->where('event', 'deleted')
        ->whereIn('subject_id', [$first->id, $second->id])
        ->count();

    expect($events)->toBe(2);
});
