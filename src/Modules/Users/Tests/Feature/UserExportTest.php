<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Users\Tests\Support\UserTestFixtures;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    UserTestFixtures::seedPermissions();
    $this->admin = UserTestFixtures::superAdmin();
});

it('exports CSV with the list filters applied', function (): void {
    User::factory()->create(['email' => 'ada@example.com', 'first_name' => 'Ada']);
    // Pin every searchable column: a random Faker last name or username can
    // contain "ada" and make this assertion fail intermittently.
    User::factory()->create([
        'email' => 'bob@example.com',
        'first_name' => 'Bob',
        'last_name' => 'Barker',
        'username' => 'bob.barker',
    ]);

    $response = $this->actingAs($this->admin)
        ->get('/users/export/csv?search=ada')
        ->assertOk();

    $content = $response->streamedContent();

    expect($content)
        ->toContain('First name')
        ->toContain('ada@example.com')
        ->not->toContain('bob@example.com');
});

it('exports XLSX as a real ZIP container', function (): void {
    User::factory()->create();

    $content = $this->actingAs($this->admin)
        ->get('/users/export/xlsx')
        ->assertOk()
        ->streamedContent();

    expect($content)->toStartWith('PK');
});

it('exports PDF rendered from the module Blade view', function (): void {
    User::factory()->create();

    $response = $this->actingAs($this->admin)
        ->get('/users/export/pdf')
        ->assertOk();

    expect($response->getContent())->toStartWith('%PDF');
});

it('forbids an export without EXPORT_USER', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/users/export/csv')
        ->assertForbidden();
});

it('answers 401 to a guest JSON export request', function (): void {
    $this->getJson('/users/export/csv')->assertUnauthorized();
});
