<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * @param array<string, mixed> $overrides
 * @return array<string, mixed>
 */
function savedViewPayload(array $overrides = []): array
{
    return [
        'resource' => 'users',
        'name' => 'Trashed admins',
        'query' => [
            'search' => 'ada',
            'sort_field' => 'created_at',
            'sort_order' => -1,
            'per_page' => 25,
            'filters' => [
                'status' => 'trashed',
                'date_from' => '2026-01-01',
            ],
        ],
        ...$overrides,
    ];
}

it('rejects a guest', function (): void {
    $this->getJson('/saved-views?resource=users')->assertUnauthorized();
    $this->postJson('/saved-views', savedViewPayload())->assertUnauthorized();
});

it('stores a view for the authenticated user', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson('/saved-views', savedViewPayload())
        ->assertCreated()
        ->assertJsonPath('view.resource', 'users')
        ->assertJsonPath('view.name', 'Trashed admins')
        ->assertJsonPath('view.search', 'ada')
        ->assertJsonPath('view.sort_field', 'created_at')
        ->assertJsonPath('view.sort_order', -1)
        ->assertJsonPath('view.per_page', 25)
        ->assertJsonPath('view.filters.status', 'trashed');

    $this->assertDatabaseHas('saved_views', [
        'user_id' => $user->id,
        'resource' => 'users',
        'name' => 'Trashed admins',
    ]);
});

it('updates a view with the same name instead of duplicating it', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson('/saved-views', savedViewPayload())
        ->assertCreated();

    $this->actingAs($user)
        ->postJson('/saved-views', savedViewPayload([
            'query' => [
                'search' => 'grace',
                'sort_field' => 'email',
                'sort_order' => 1,
                'per_page' => 50,
                'filters' => ['status' => 'all'],
            ],
        ]))
        ->assertOk()
        ->assertJsonPath('view.search', 'grace')
        ->assertJsonPath('view.filters.status', 'all');

    expect($user->savedViews()->count())->toBe(1);
});

it('lists only the authenticated user views for the requested resource', function (): void {
    $owner = User::factory()->create();
    $other = User::factory()->create();

    $this->actingAs($owner)->postJson('/saved-views', savedViewPayload())->assertCreated();
    $this->actingAs($owner)
        ->postJson('/saved-views', savedViewPayload(['resource' => 'backups', 'name' => 'Quarantined dumps']))
        ->assertCreated();
    $this->actingAs($other)
        ->postJson('/saved-views', savedViewPayload(['name' => 'Someone else']))
        ->assertCreated();

    $this->actingAs($owner)
        ->getJson('/saved-views?resource=users')
        ->assertOk()
        ->assertJsonCount(1, 'views')
        ->assertJsonPath('views.0.name', 'Trashed admins');
});

it('accepts the appointment filter allowlist', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson('/saved-views', savedViewPayload([
            'resource' => 'appointments',
            'name' => 'Houston inspections',
            'query' => [
                'sort_field' => 'starts_at',
                'sort_order' => 1,
                'per_page' => 25,
                'filters' => [
                    'status' => 'scheduled',
                    'kind' => 'inspection',
                    'region' => 'houston',
                    'date_from' => '2026-01-01',
                    'date_to' => '2026-12-31',
                ],
            ],
        ]))
        ->assertCreated()
        ->assertJsonPath('view.resource', 'appointments')
        ->assertJsonPath('view.filters.kind', 'inspection');
});

it('rejects an unknown resource', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson('/saved-views', savedViewPayload(['resource' => 'payroll']))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['resource']);

    $this->actingAs($user)
        ->getJson('/saved-views?resource=payroll')
        ->assertUnprocessable();
});

it('rejects an unknown filter key', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson('/saved-views', savedViewPayload([
            'query' => [
                'filters' => ['salary' => '100000'],
            ],
        ]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['query.filters']);
});

it('rejects a filter value that is not text', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson('/saved-views', savedViewPayload([
            'query' => [
                'filters' => ['status' => ['nested' => 'value']],
            ],
        ]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['query.filters']);
});

it('rejects a name over the limit and trims it', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson('/saved-views', savedViewPayload(['name' => str_repeat('a', 61)]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name']);

    $this->actingAs($user)
        ->postJson('/saved-views', savedViewPayload(['name' => '  Weekly   review  ']))
        ->assertCreated()
        ->assertJsonPath('view.name', 'Weekly review');
});

it('never reads or deletes another user view', function (): void {
    $owner = User::factory()->create();
    $other = User::factory()->create();

    $uuid = $this->actingAs($owner)
        ->postJson('/saved-views', savedViewPayload())
        ->assertCreated()
        ->json('view.uuid');

    $this->actingAs($other)
        ->getJson('/saved-views?resource=users')
        ->assertOk()
        ->assertJsonCount(0, 'views');

    $this->actingAs($other)
        ->deleteJson('/saved-views/'.$uuid)
        ->assertNotFound();

    $this->actingAs($owner)->deleteJson('/saved-views/'.$uuid)->assertNoContent();

    expect($owner->savedViews()->count())->toBe(0);
});

it('caps the number of views per screen', function (): void {
    $user = User::factory()->create();

    for ($index = 1; $index <= 20; $index++) {
        $this->actingAs($user)
            ->postJson('/saved-views', savedViewPayload(['name' => 'View '.$index]))
            ->assertCreated();
    }

    $this->actingAs($user)
        ->postJson('/saved-views', savedViewPayload(['name' => 'View 21']))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name']);
});
