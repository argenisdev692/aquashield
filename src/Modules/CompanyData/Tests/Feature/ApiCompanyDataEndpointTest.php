<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Modules\CompanyData\Infrastructure\Persistence\CompanyData;
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

it('updates the singleton with a token and the update permission', function (): void {
    $this->user->givePermissionTo('UPDATE_COMPANY_DATA');
    Sanctum::actingAs($this->user, ['*']);

    $this->putJson('/api/company-data', [
        'name' => 'Victor Lara',
        'company_name' => 'AquaShield Restoration USA',
        'email' => 'info@aquashieldrestorationusa.com',
    ])->assertOk()->assertJsonPath('company_name', 'AquaShield Restoration USA');

    $this->assertDatabaseCount('company_data', 1);
    $this->assertDatabaseHas('company_data', ['user_id' => $this->user->id]);
});
