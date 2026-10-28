<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\CompanyData\Infrastructure\Persistence\CompanyData;
use Shared\Infrastructure\Company\CompanyProfile;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    Permission::findOrCreate('VIEW_COMPANY_DATA', 'web');
    Permission::findOrCreate('UPDATE_COMPANY_DATA', 'web');

    $this->user = User::factory()->create();
});

/**
 * Provision the singleton the way the seeder does: company fields through the
 * fill allowlist, ownership through an explicit assignment.
 *
 * @param array<string, mixed> $attributes
 */
function companyDataRecord(User $owner, array $attributes = []): CompanyData
{
    $company = new CompanyData;

    $company->fill([
        'name' => 'Victor Lara',
        'company_name' => 'AquaShield Restoration USA',
        'email' => 'info@aquashieldrestorationusa.com',
        ...$attributes,
    ]);

    $company->user_id = $owner->id;
    $company->save();

    return $company;
}

it('redirects a guest to the login page', function (): void {
    $this->get('/company-data')->assertRedirect('/login');
});

it('answers 401 to a guest JSON request', function (): void {
    $this->getJson('/company-data')->assertUnauthorized();
    $this->putJson('/company-data', [])->assertUnauthorized();
});

it('forbids a user without the view permission', function (): void {
    $this->actingAs($this->user)->getJson('/company-data')->assertForbidden();
});

it('forbids a user without the update permission', function (): void {
    $this->actingAs($this->user)->putJson('/company-data', [])->assertForbidden();
});

it('returns 404 when the singleton has not been provisioned', function (): void {
    $this->user->givePermissionTo('VIEW_COMPANY_DATA');

    $this->actingAs($this->user)->getJson('/company-data')->assertNotFound();
});

it('returns the singleton without an internal id or owner id', function (): void {
    $this->user->givePermissionTo('VIEW_COMPANY_DATA');

    companyDataRecord($this->user);

    $this->actingAs($this->user)
        ->getJson('/company-data')
        ->assertOk()
        ->assertJsonPath('company_name', 'AquaShield Restoration USA')
        ->assertJsonMissingPath('id')
        ->assertJsonMissingPath('user_id')
        ->assertJsonMissingPath('signature_path');
});

it('rejects invalid input with 422', function (): void {
    $this->user->givePermissionTo('UPDATE_COMPANY_DATA');

    $this->actingAs($this->user)
        ->putJson('/company-data', [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name', 'company_name', 'email']);
});

it('rejects a client supplied user_id with 422', function (): void {
    $this->user->givePermissionTo('UPDATE_COMPANY_DATA');

    $this->actingAs($this->user)
        ->putJson('/company-data', [
            'name' => 'Victor Lara',
            'company_name' => 'AquaShield Restoration USA',
            'email' => 'info@aquashieldrestorationusa.com',
            'user_id' => $this->user->id + 1,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['user_id']);
});

it('creates then updates one singleton row and assigns the authenticated owner', function (): void {
    $this->user->givePermissionTo('UPDATE_COMPANY_DATA');

    $payload = [
        'name' => 'Victor Lara',
        'company_name' => 'AquaShield Restoration USA',
        'email' => 'info@aquashieldrestorationusa.com',
        'city' => 'Houston',
    ];

    $this->actingAs($this->user)
        ->putJson('/company-data', $payload)
        ->assertOk()
        ->assertJsonPath('company_name', 'AquaShield Restoration USA')
        ->assertJsonPath('city', 'Houston');

    $this->assertDatabaseCount('company_data', 1);
    $this->assertDatabaseHas('company_data', ['user_id' => $this->user->id]);

    $this->actingAs($this->user)
        ->putJson('/company-data', [
            ...$payload,
            'company_name' => 'AquaShield Restoration LLC',
        ])
        ->assertOk()
        ->assertJsonPath('company_name', 'AquaShield Restoration LLC');

    $this->assertDatabaseCount('company_data', 1);
});

it('flushes the company profile cache when the singleton changes', function (): void {
    $this->user->givePermissionTo('UPDATE_COMPANY_DATA');

    companyDataRecord($this->user, ['company_name' => 'Before']);

    expect(CompanyProfile::data()['name'])->toBe('Before');

    $this->actingAs($this->user)
        ->putJson('/company-data', [
            'name' => 'Victor Lara',
            'company_name' => 'After',
            'email' => 'info@aquashieldrestorationusa.com',
        ])
        ->assertOk();

    expect(CompanyProfile::data()['name'])->toBe('After');
});
