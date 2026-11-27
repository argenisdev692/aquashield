<?php

declare(strict_types=1);

use App\Models\Permission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Modules\CompanyData\Models\CompanyData;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    Permission::findOrCreate('VIEW_COMPANY_DATA', 'web');
    Permission::findOrCreate('UPDATE_COMPANY_DATA', 'web');

    $this->user = User::factory()->create();
});

function apiCompanyRecord(User $owner): CompanyData
{
    $company = new CompanyData;

    $company->fill([
        'company_name' => 'AquaShield Restoration USA',
        'email' => 'info@aquashieldrestorationusa.com',
        'bank_iban' => 'PT50 0002 0123 1234 5678 9015 4',
    ]);

    $company->user_id = $owner->id;
    $company->save();

    return $company;
}

it('requires a token for the management read', function (): void {
    $this->getJson('/api/company-data/manage')->assertUnauthorized();
});

it('forbids a token without the view permission', function (): void {
    Sanctum::actingAs($this->user, ['*']);

    $this->getJson('/api/company-data/manage')->assertForbidden();
});

it('forbids an unverified token', function (): void {
    $user = User::factory()->unverified()->create();
    $user->givePermissionTo('VIEW_COMPANY_DATA');

    Sanctum::actingAs($user, ['*']);

    $this->getJson('/api/company-data/manage')->assertForbidden();
});

it('forbids a token for an account that must change its password', function (): void {
    $user = User::factory()->create(['must_change_password' => true]);
    $user->givePermissionTo('VIEW_COMPANY_DATA');

    Sanctum::actingAs($user, ['*']);

    $this->getJson('/api/company-data/manage')->assertForbidden();
});

it('returns the full profile to a token with the view permission', function (): void {
    $this->user->givePermissionTo('VIEW_COMPANY_DATA');
    apiCompanyRecord($this->user);

    Sanctum::actingAs($this->user, ['*']);

    $this->getJson('/api/company-data/manage')
        ->assertOk()
        ->assertJsonPath('company_name', 'AquaShield Restoration USA')
        ->assertJsonPath('bank_iban', 'PT50 0002 0123 1234 5678 9015 4')
        ->assertJsonMissingPath('user_id');
});

it('forbids a token without the update permission', function (): void {
    Sanctum::actingAs($this->user, ['*']);

    $this->putJson('/api/company-data', [])->assertForbidden();
});

it('rejects invalid input with 422', function (): void {
    $this->user->givePermissionTo('UPDATE_COMPANY_DATA');
    Sanctum::actingAs($this->user, ['*']);

    $this->putJson('/api/company-data')
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name', 'company_name', 'email']);
});

it('answers 404 when the unprovisioned singleton is updated', function (): void {
    $this->user->givePermissionTo('UPDATE_COMPANY_DATA');
    Sanctum::actingAs($this->user, ['*']);

    $this->putJson('/api/company-data', [
        'name' => 'Victor Lara',
        'company_name' => 'AquaShield Restoration USA',
        'email' => 'info@aquashieldrestorationusa.com',
        'updated_at' => now()->toJSON(),
    ])->assertNotFound();

    $this->assertDatabaseCount('company_data', 0);
});

it('updates the provisioned singleton with a token and the update permission', function (): void {
    $this->user->givePermissionTo('UPDATE_COMPANY_DATA');

    $company = apiCompanyRecord($this->user);

    Sanctum::actingAs($this->user, ['*']);

    $this->putJson('/api/company-data', [
        'name' => 'Victor Lara',
        'company_name' => 'AquaShield Restoration LLC',
        'email' => 'info@aquashieldrestorationusa.com',
        'updated_at' => $company->updated_at?->toJSON(),
    ])->assertOk()->assertJsonPath('company_name', 'AquaShield Restoration LLC');

    $this->assertDatabaseCount('company_data', 1);
    $this->assertDatabaseHas('company_data', ['user_id' => $this->user->id]);

    expect(CompanyData::current()?->uuid)->toBe($company->uuid);
});
