<?php

declare(strict_types=1);

use App\Models\User;
use Database\Seeders\CompanyDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\CompanyData\Infrastructure\Persistence\CompanyData;
use Shared\Infrastructure\Company\CompanyProfile;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('serves public branding without authentication', function (): void {
    $owner = User::factory()->create();

    $company = new CompanyData;

    $company->fill([
        'company_name' => 'AquaShield Restoration USA',
        'description' => 'Water damage restoration in Houston.',
        'email' => 'info@aquashieldrestorationusa.com',
        'phone' => '+17135876423',
        'address' => '3733 Westheimer Rd. Ste 1-4583, Houston, TX 77027',
        'country' => 'United States',
        'country_code' => 'US',
        'website' => 'https://aquashieldrestorationusa.com',
        'facebook_link' => 'https://www.facebook.com/aquashieldrestorationusa/',
    ]);

    $company->user_id = $owner->id;
    $company->logo_path = 'img/logo.webp';
    $company->logo_white_path = 'img/logo-white.webp';
    $company->mark_path = 'img/logo-mark.webp';
    $company->save();

    $this->getJson('/api/company-data')
        ->assertOk()
        ->assertJsonPath('name', 'AquaShield Restoration USA')
        ->assertJsonPath('description', 'Water damage restoration in Houston.')
        ->assertJsonPath('logo_url', url('img/logo.webp'))
        ->assertJsonPath('logo_white_url', url('img/logo-white.webp'))
        ->assertJsonPath('mark_url', url('img/logo-mark.webp'))
        ->assertJsonPath('country_code', 'US')
        ->assertJsonPath('socials.facebook', 'https://www.facebook.com/aquashieldrestorationusa/')
        ->assertJsonMissingPath('user_id')
        ->assertJsonMissingPath('bank_iban')
        ->assertJsonMissingPath('nif_nipc')
        ->assertJsonMissingPath('signature_path');
});

it('falls back to the app branding when no company row exists', function (): void {
    CompanyProfile::forget();

    $this->getJson('/api/company-data')
        ->assertOk()
        ->assertJsonPath('name', (string) config('app.name'))
        ->assertJsonPath('logo_url', url('img/logo.webp'));
});

it('seeds the branding fields used by the welcome page', function (): void {
    User::factory()->create(['email' => 'info@aquashieldrestorationusa.com']);

    $this->seed(CompanyDataSeeder::class);

    $company = CompanyData::query()->firstOrFail();

    expect($company->company_name)->toBe('AquaShield Restoration USA')
        ->and($company->logo_path)->toBe('img/logo.webp')
        ->and($company->logo_white_path)->toBe('img/logo-white.webp')
        ->and($company->mark_path)->toBe('img/logo-mark.webp')
        ->and($company->description)->toContain('Water damage restoration');
});
