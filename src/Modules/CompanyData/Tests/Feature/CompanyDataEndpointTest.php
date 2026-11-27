<?php

declare(strict_types=1);

use App\Models\Permission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\CompanyData\Models\CompanyData;
use Shared\Infrastructure\Company\CompanyProfile;
use Spatie\Activitylog\Models\Activity;
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

it('answers 404 when an unprovisioned singleton is updated', function (): void {
    $this->user->givePermissionTo('UPDATE_COMPANY_DATA');

    $this->actingAs($this->user)
        ->putJson('/company-data', [
            'name' => 'Victor Lara',
            'company_name' => 'AquaShield Restoration USA',
            'email' => 'info@aquashieldrestorationusa.com',
            'updated_at' => now()->toJSON(),
        ])
        ->assertNotFound();

    $this->assertDatabaseCount('company_data', 0);
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
            'updated_at' => now()->toJSON(),
            'user_id' => $this->user->id + 1,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['user_id']);
});

it('rejects a non-http public link with 422', function (): void {
    $this->user->givePermissionTo('UPDATE_COMPANY_DATA');

    $this->actingAs($this->user)
        ->putJson('/company-data', [
            'name' => 'Victor Lara',
            'company_name' => 'AquaShield Restoration USA',
            'email' => 'info@aquashieldrestorationusa.com',
            'updated_at' => now()->toJSON(),
            'website' => 'javascript:alert(1)',
            'facebook_link' => 'data:text/html,<script>alert(1)</script>',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['website', 'facebook_link']);
});

it('updates the provisioned singleton in place and preserves the owner', function (): void {
    $this->user->givePermissionTo('UPDATE_COMPANY_DATA');

    $company = companyDataRecord($this->user, [
        'company_name' => 'AquaShield Restoration USA',
        'city' => 'Houston',
    ]);

    $this->actingAs($this->user)
        ->putJson('/company-data', [
            'name' => 'Victor Lara',
            'company_name' => 'AquaShield Restoration LLC',
            'email' => 'info@aquashieldrestorationusa.com',
            'updated_at' => $company->updated_at?->toJSON(),
            'city' => 'Houston',
        ])
        ->assertOk()
        ->assertJsonPath('company_name', 'AquaShield Restoration LLC')
        ->assertJsonPath('city', 'Houston');

    $this->assertDatabaseCount('company_data', 1);
    $this->assertDatabaseHas('company_data', [
        'user_id' => $this->user->id,
        'company_name' => 'AquaShield Restoration LLC',
    ]);

    expect(CompanyData::current()?->uuid)->toBe($company->uuid);
});

it('rejects a stale full-profile write with 409', function (): void {
    $this->user->givePermissionTo('UPDATE_COMPANY_DATA');
    $company = companyDataRecord($this->user, ['company_name' => 'Current']);

    $this->actingAs($this->user)
        ->putJson('/company-data', [
            'name' => 'Victor Lara',
            'company_name' => 'Stale',
            'email' => 'info@aquashieldrestorationusa.com',
            'updated_at' => now()->subMinute()->toJSON(),
        ])
        ->assertStatus(409);

    expect($company->refresh()->company_name)->toBe('Current');
});

it('audits financial field names without values', function (): void {
    $this->user->givePermissionTo('UPDATE_COMPANY_DATA');

    $company = companyDataRecord($this->user, ['bank_iban' => 'PT50 OLD']);

    $this->actingAs($this->user)
        ->putJson('/company-data', [
            'name' => 'Victor Lara',
            'company_name' => 'AquaShield Restoration USA',
            'email' => 'info@aquashieldrestorationusa.com',
            'updated_at' => $company->updated_at?->toJSON(),
            'bank_iban' => 'PT50 NEW SECRET',
        ])
        ->assertOk();

    $activity = Activity::query()
        ->where('event', 'financial-fields-updated')
        ->latest('id')
        ->first();

    expect($activity)->not->toBeNull()
        ->and($activity->properties->get('changed'))->toContain('bank_iban')
        ->and(json_encode($activity->properties->toArray()))->not->toContain('PT50 NEW SECRET');
});

it('flushes the company profile cache when the singleton changes', function (): void {
    $this->user->givePermissionTo('UPDATE_COMPANY_DATA');

    $company = companyDataRecord($this->user, ['company_name' => 'Before']);

    expect(CompanyProfile::data()['name'])->toBe('Before');

    $this->actingAs($this->user)
        ->putJson('/company-data', [
            'name' => 'Victor Lara',
            'company_name' => 'After',
            'email' => 'info@aquashieldrestorationusa.com',
            'updated_at' => $company->updated_at?->toJSON(),
        ])
        ->assertOk();

    expect(CompanyProfile::data()['name'])->toBe('After');
});
