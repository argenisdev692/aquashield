<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Modules\CompanyData\Infrastructure\Persistence\CompanyData;
use Shared\Infrastructure\Company\CompanyProfile;

uses(RefreshDatabase::class);

it('shares the seeded company branding with the welcome page', function (): void {
    $owner = User::factory()->create();

    $company = new CompanyData;

    $company->fill([
        'company_name' => 'AquaShield Restoration USA',
        'email' => 'info@aquashieldrestorationusa.com',
        'phone' => '+17135876423',
    ]);

    $company->user_id = $owner->id;
    $company->logo_path = 'img/logo.webp';
    $company->logo_white_path = 'img/logo-white.webp';
    $company->mark_path = 'img/logo-mark.webp';
    $company->save();

    CompanyProfile::forget();

    $this->get('/')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Welcome')
            ->where('company.name', 'AquaShield Restoration USA')
            ->where('company.mark_url', url('img/logo-mark.webp'))
            ->where('company.phone', '+17135876423'));
});
