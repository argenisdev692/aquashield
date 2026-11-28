<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;
use Modules\LeadAppointment\Domain\Enums\LeadStatus;
use Modules\LeadAppointment\Infrastructure\Persistence\Eloquent\Models\Lead;
use Modules\LeadAppointment\Tests\Support\LeadAppointmentFixtures;
use Modules\Users\Enums\UserStatus;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    Mail::fake();
    LeadAppointmentFixtures::seedPermissions();
    $this->admin = LeadAppointmentFixtures::superAdmin();
});

it('redirects guests and refuses users without the lead permission', function (): void {
    $this->get('/leads')->assertRedirect('/login');

    $this->actingAs(User::factory()->create())
        ->get('/leads')
        ->assertForbidden();
});

it('renders the leads index with the first page and the filter catalog', function (): void {
    $lead = LeadAppointmentFixtures::lead();
    $lead->forceFill(['first_name' => 'Ada', 'last_name' => 'Lovelace'])->save();

    $this->actingAs($this->admin)
        ->get('/leads?search=Ada&sort=-created_at')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Leads/Index')
            ->has('rows', 1)
            ->where('rows.0.uuid', $lead->uuid)
            ->where('total', 1)
            ->where('canCreate', true)
            ->where('canUpdate', true)
            ->where('canDelete', true)
            ->where('canExport', true)
            ->has('options.statuses', 7)
            ->has('options.sources', 7)
            ->has('options.regions', 3)
            ->has('options.owners', 1)
        );
});

it('excludes suspended users from the assignable owner list', function (): void {
    User::factory()->create([
        'status' => UserStatus::Suspended,
        'suspended_at' => now(),
    ]);

    $this->actingAs($this->admin)
        ->get('/leads/create')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('options.owners', 1));
});

it('renders the pipeline board grouped by stage', function (): void {
    LeadAppointmentFixtures::lead();
    Lead::factory()->create(['status' => LeadStatus::Contacted->value]);

    $this->actingAs($this->admin)
        ->get('/leads/board')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Leads/Board')
            ->has('columns', 7)
            ->where('columns.0.status', 'new')
            ->where('columns.0.total', 1)
            ->has('columns.0.leads', 1)
            ->where('columns.1.status', 'contacted')
            ->where('columns.1.total', 1)
            ->has('transitions.new', 4)
            ->where('canUpdate', true)
        );
});

it('renders the create and edit screens', function (): void {
    $lead = LeadAppointmentFixtures::lead();

    $this->actingAs($this->admin)
        ->get('/leads/create')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Leads/Create')
            ->has('options.owners', 1)
            ->has('googleMapsApiKey')
        );

    $this->actingAs($this->admin)
        ->get('/leads/'.$lead->uuid.'/edit')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Leads/Edit')
            ->where('lead.uuid', $lead->uuid)
            ->has('googleMapsApiKey')
        );
});

it('creates a manual lead through the Inertia form and redirects to its file', function (): void {
    $this->actingAs($this->admin)
        ->post('/leads', [
            'first_name' => 'Grace',
            'last_name' => 'Hopper',
            'email' => 'grace@example.com',
            'phone' => '+17135550123',
            'address' => '1 Navy Way',
            'address_2' => 'Suite 400',
            'source' => 'meta',
        ])
        ->assertRedirect();

    $lead = Lead::query()->where('email', 'grace@example.com')->firstOrFail();

    expect($lead->source->value)->toBe('manual')
        ->and($lead->source_detail)->toBe('Admin')
        ->and($lead->address_2)->toBe('Suite 400')
        ->and($lead->status)->toBe(LeadStatus::New);
});

it('moves a lead stage through the Inertia control', function (): void {
    $lead = LeadAppointmentFixtures::lead();

    $this->actingAs($this->admin)
        ->from('/leads/board')
        ->post('/leads/'.$lead->uuid.'/status', ['status' => 'contacted'])
        ->assertRedirect('/leads/board');

    expect($lead->refresh()->status)->toBe(LeadStatus::Contacted);
});

it('rejects an illegal stage change with a redirect error instead of a 500', function (): void {
    $lead = LeadAppointmentFixtures::lead();

    $this->actingAs($this->admin)
        ->from('/leads/board')
        ->post('/leads/'.$lead->uuid.'/status', ['status' => 'won'])
        ->assertRedirect('/leads/board')
        ->assertSessionHasErrors('status');

    expect($lead->refresh()->status)->toBe(LeadStatus::New);
});

it('renders the lead file with its activity timeline', function (): void {
    $lead = LeadAppointmentFixtures::lead();

    $this->actingAs($this->admin)
        ->get('/leads/'.$lead->uuid)
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Leads/Show')
            ->where('lead.uuid', $lead->uuid)
            ->has('activities', 0)
            ->has('transitions')
            ->where('canUpdate', true)
            ->where('canDelete', true)
        );
});
