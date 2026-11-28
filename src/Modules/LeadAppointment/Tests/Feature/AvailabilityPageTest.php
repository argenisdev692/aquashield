<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Modules\LeadAppointment\Infrastructure\Persistence\Eloquent\Models\AvailabilityException;
use Modules\LeadAppointment\Infrastructure\Persistence\Eloquent\Models\AvailabilityRule;
use Modules\LeadAppointment\Tests\Support\LeadAppointmentFixtures;
use Shared\Domain\Enums\Permission as PermissionName;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    LeadAppointmentFixtures::seedPermissions();
    $this->admin = LeadAppointmentFixtures::superAdmin();
});

it('renders the availability page with the exception rows and the initial month', function (): void {
    LeadAppointmentFixtures::companyRule(1, '08:00', '17:00');

    $exception = AvailabilityException::factory()->create([
        'date' => '2026-12-24',
        'source' => 'manual',
        'reason' => 'Christmas Eve',
    ]);

    $this->actingAs($this->admin)
        ->get('/availability')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Availability/Index')
            ->has('rows', 1)
            ->where('rows.0.uuid', $exception->uuid)
            ->where('rows.0.date', '2026-12-24')
            ->where('rows.0.deleted_at', null)
            ->has('rules', 1)
            ->where('rules.0.day_of_week', 1)
            ->has('calendar.from')
            ->has('calendar.to')
            ->has('calendar.today')
            ->where('calendar.maxDays', (int) config('lead-appointment.calendar_max_days', 92))
            ->where('canCreate', true)
            ->where('canExport', true)
            ->where('canViewCalendar', true));
});

it('filters the table by source, state and soft-deleted record state', function (): void {
    $manual = AvailabilityException::factory()->create([
        'date' => '2026-05-01',
        'source' => 'manual',
    ]);
    $holiday = AvailabilityException::factory()->create([
        'date' => '2026-07-04',
        'source' => 'holiday',
        'reason' => 'Independence Day',
    ]);
    $trashed = AvailabilityException::factory()->create(['date' => '2026-08-14']);
    $trashed->delete();

    $this->actingAs($this->admin)
        ->get('/availability?filter[source]=holiday')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('rows', 1)
            ->where('rows.0.uuid', $holiday->uuid));

    $this->actingAs($this->admin)
        ->get('/availability?filter[record_state]=trashed')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('rows', 1)
            ->where('rows.0.uuid', $trashed->uuid)
            ->whereNot('rows.0.deleted_at', null));

    $this->actingAs($this->admin)
        ->get('/availability?filter[availability]=closed&filter[source]=manual')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('rows', 1)
            ->where('rows.0.uuid', $manual->uuid));
});

it('prefills the create form with the day the calendar supplied', function (): void {
    $this->actingAs($this->admin)
        ->get('/availability/create?date=2026-12-24')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Availability/Create')
            ->where('defaults.date', '2026-12-24')
            ->where('defaults.is_available', false)
            ->has('timezone'));
});

it('redirects the calendar to the edit form when the day already has an exception', function (): void {
    $exception = AvailabilityException::factory()->create(['date' => '2026-12-24']);

    $this->actingAs($this->admin)
        ->get('/availability/create?date=2026-12-24')
        ->assertRedirect('/availability/'.$exception->uuid.'/edit');

    // Any other day still opens the create form.
    $this->actingAs($this->admin)
        ->get('/availability/create?date=2026-12-25')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Availability/Create'));
});

it('keeps a create-only user on the create form for a day that already exists', function (): void {
    $creator = User::factory()->create();
    $creator->givePermissionTo(PermissionName::CreateAvailabilityException->value);

    AvailabilityException::factory()->create(['date' => '2026-12-24']);

    // Redirecting to the edit route would answer 403 for this caller, so the
    // duplicate-day conflict has to stay a form error instead.
    $this->actingAs($creator)
        ->get('/availability/create?date=2026-12-24')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Availability/Create'));
});

it('opens the edit form for an update-only user', function (): void {
    $editor = User::factory()->create();
    $editor->givePermissionTo(PermissionName::UpdateAvailabilityException->value);

    $exception = AvailabilityException::factory()->create(['date' => '2026-12-24']);

    // The month grid links every day to the read route; the controller turns it
    // into the edit form for a caller who may update but cannot create.
    $this->actingAs($editor)
        ->get('/availability/create?date=2026-12-24')
        ->assertRedirect('/availability/'.$exception->uuid.'/edit');
});

it('sends an update-only user back when the day has no exception', function (): void {
    $editor = User::factory()->create();
    $editor->givePermissionTo(PermissionName::UpdateAvailabilityException->value);

    // Nothing to edit and no permission to create: the create form's save would
    // always answer 403, so the caller returns to the table with a reason.
    $this->actingAs($editor)
        ->get('/availability/create?date=2026-12-25')
        ->assertRedirect('/availability')
        ->assertSessionHas('status');
});

it('keeps the availability write gated on the create permission', function (): void {
    $editor = User::factory()->create();
    $editor->givePermissionTo(PermissionName::UpdateAvailabilityException->value);

    $this->actingAs($editor)
        ->post('/availability', [
            'date' => '2026-12-24',
            'is_available' => false,
            'reason' => 'Holiday',
        ])
        ->assertForbidden();
});

it('renders the edit form for an existing exception', function (): void {
    $exception = AvailabilityException::factory()->create([
        'date' => '2026-12-24',
        'reason' => 'Christmas Eve',
    ]);

    $this->actingAs($this->admin)
        ->get('/availability/'.$exception->uuid.'/edit')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Availability/Edit')
            ->where('exception.uuid', $exception->uuid)
            ->where('exception.date', '2026-12-24')
            ->where('exception.source', 'manual'));
});

it('stores an exception and redirects to the calendar', function (): void {
    $this->actingAs($this->admin)
        ->post('/availability', [
            'date' => '2026-12-24',
            'is_available' => false,
            'reason' => 'Christmas Eve',
        ])
        ->assertRedirect('/availability')
        ->assertSessionHas('status');

    expect(AvailabilityException::query()->whereDate('date', '2026-12-24')->sole()->reason)
        ->toBe('Christmas Eve');
});

it('rejects a duplicate date with a field error instead of a 500', function (): void {
    AvailabilityException::factory()->create(['date' => '2026-12-24']);

    $this->actingAs($this->admin)
        ->post('/availability', [
            'date' => '2026-12-24',
            'is_available' => false,
        ])
        ->assertSessionHasErrors('date');

    expect(AvailabilityException::query()->whereDate('date', '2026-12-24')->count())->toBe(1);
});

it('updates, soft-deletes and restores an exception', function (): void {
    $exception = AvailabilityException::factory()->create(['date' => '2026-12-24']);

    $this->actingAs($this->admin)
        ->put('/availability/'.$exception->uuid, [
            'date' => '2026-12-24',
            'is_available' => true,
            'start_time' => '09:00',
            'end_time' => '13:00',
            'reason' => 'Crew works the morning',
        ])
        ->assertRedirect('/availability');

    expect($exception->refresh()->is_available)->toBeTrue()
        ->and($exception->start_time)->toBe('09:00');

    $this->actingAs($this->admin)
        ->delete('/availability/'.$exception->uuid)
        ->assertRedirect();

    expect($exception->refresh()->trashed())->toBeTrue();

    $this->actingAs($this->admin)
        ->patch('/availability/'.$exception->uuid.'/restore')
        ->assertRedirect();

    expect($exception->refresh()->trashed())->toBeFalse();
});

it('exports the filtered exceptions with the same contract as the list', function (): void {
    AvailabilityException::factory()->create(['date' => '2026-05-01', 'source' => 'manual']);
    AvailabilityException::factory()->create([
        'date' => '2026-07-04',
        'source' => 'holiday',
        'reason' => 'Independence Day',
    ]);

    $response = $this->actingAs($this->admin)
        ->get('/availability/export/csv?filter[source]=holiday');

    $response->assertOk();

    expect((string) $response->headers->get('content-disposition'))
        ->toContain('availability-exceptions.csv');

    $entry = Activity::query()->where('event', 'availability_exception.exported')->sole();

    expect($entry->log_name)->toBe('availability')
        ->and($entry->properties->toArray()['rows'])->toBe(1)
        ->and($entry->properties->toArray()['filters']['source'])->toBe('holiday');
});

it('forbids the page, the calendar and the export without the matching permissions', function (): void {
    $outsider = User::factory()->create();

    $this->get('/availability')->assertRedirect('/login');

    $this->actingAs($outsider)->get('/availability')->assertForbidden();
    $this->actingAs($outsider)->get('/availability/create')->assertForbidden();
    $this->actingAs($outsider)->post('/availability', [])->assertForbidden();
    $this->actingAs($outsider)->get('/availability/export/csv')->assertForbidden();
});

it('keeps the JSON resource and the page route on separate contracts', function (): void {
    $exception = AvailabilityException::factory()->create(['date' => '2026-12-24']);

    $this->actingAs($this->admin)
        ->getJson('/availability-exceptions?from=2026-12-01&to=2026-12-31')
        ->assertOk()
        ->assertJsonPath('data.0.uuid', $exception->uuid);

    $this->actingAs($this->admin)
        ->get('/availability')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Availability/Index'));
});

it('bounds the deleted-window panel and reports truncation', function (): void {
    config(['lead-appointment.availability_rules.max_trashed_read' => 2]);

    $rules = AvailabilityRule::factory()->count(3)->create();
    $rules->each->delete();

    $this->actingAs($this->admin)
        ->get('/availability')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('trashedRules', 2)
            ->where('trashedRulesLimited', true));
});

it('reads back a deleted weekly window and restores it', function (): void {
    $rule = LeadAppointmentFixtures::companyRule(3, '09:00', '13:00');
    $rule->delete();

    $this->actingAs($this->admin)
        ->get('/availability')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('rules', 0)
            ->has('trashedRules', 1)
            ->where('trashedRules.0.uuid', $rule->uuid)
            ->where('trashedRules.0.day_of_week', 3)
            ->where('trashedRules.0.start_time', '09:00')
            ->where('canRestoreRule', true));

    $this->actingAs($this->admin)
        ->patch('/availability-rules/'.$rule->uuid.'/restore')
        ->assertOk();

    expect($rule->refresh()->trashed())->toBeFalse();

    $this->actingAs($this->admin)
        ->get('/availability')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('rules', 1)
            ->has('trashedRules', 0));
});
