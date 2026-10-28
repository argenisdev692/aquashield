<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Modules\CompanyData\Infrastructure\Persistence\CompanyData;
use Modules\CompanyData\Tests\Support\FakeStoragePort;
use Shared\Domain\Ports\StoragePort;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    Permission::findOrCreate('VIEW_COMPANY_DATA', 'web');
    Permission::findOrCreate('UPDATE_COMPANY_DATA', 'web');

    $this->user = User::factory()->create();

    // The page resolves asset URLs; a fake keeps rendering independent of R2
    // credentials being present in the test environment.
    $this->app->instance(StoragePort::class, new FakeStoragePort);
});

/**
 * Provision the singleton the way the seeder does.
 */
function pageCompanyRecord(User $owner): CompanyData
{
    $company = new CompanyData;

    $company->fill([
        'name' => 'Victor Lara',
        'company_name' => 'AquaShield Restoration USA',
        'email' => 'info@aquashieldrestorationusa.com',
        'city' => 'Houston',
    ]);

    $company->user_id = $owner->id;
    $company->save();

    return $company;
}

it('redirects a guest from the edit screen to the login page', function (): void {
    $this->get('/company-data/edit')->assertRedirect('/login');
});

it('forbids the edit screen without the view permission', function (): void {
    $this->actingAs($this->user)->get('/company-data/edit')->assertForbidden();
});

it('answers 404 when the singleton has not been provisioned', function (): void {
    $this->user->givePermissionTo('VIEW_COMPANY_DATA');

    $this->actingAs($this->user)->get('/company-data/edit')->assertNotFound();
});

it('renders the profile as read-only for a view-only user', function (): void {
    $this->user->givePermissionTo('VIEW_COMPANY_DATA');
    pageCompanyRecord($this->user);

    $this->actingAs($this->user)
        ->get('/company-data/edit')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('CompanyData/Edit')
            ->where('company.company_name', 'AquaShield Restoration USA')
            ->where('assets.logo_url', rtrim((string) config('app.url'), '/').'/img/logo.webp')
            ->where('assets.signature_url', null)
            ->where('canUpdate', false)
            ->missing('company.user_id'));
});

it('flags canUpdate for a user with the update permission', function (): void {
    $this->user->givePermissionTo(['VIEW_COMPANY_DATA', 'UPDATE_COMPANY_DATA']);
    pageCompanyRecord($this->user);

    $this->actingAs($this->user)
        ->get('/company-data/edit')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('CompanyData/Edit')->where('canUpdate', true));
});

it('redirects the Inertia form back to the edit screen and persists the profile', function (): void {
    $this->user->givePermissionTo('UPDATE_COMPANY_DATA');

    $this->actingAs($this->user)
        ->put('/company-data', [
            'name' => 'Victor Lara',
            'company_name' => 'AquaShield Restoration LLC',
            'email' => 'info@aquashieldrestorationusa.com',
            'city' => 'Houston',
        ])
        ->assertRedirect(route('company-data.edit'));

    $this->assertDatabaseCount('company_data', 1);
    $this->assertDatabaseHas('company_data', ['company_name' => 'AquaShield Restoration LLC']);
});
