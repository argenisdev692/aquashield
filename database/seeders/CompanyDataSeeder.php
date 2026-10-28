<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Modules\CompanyData\Infrastructure\Persistence\CompanyData;
use Shared\Infrastructure\Company\CompanyProfile;

/**
 * Seeds the CompanyData singleton (AquaShield Restoration USA).
 */
class CompanyDataSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->createMainCompany();

        CompanyProfile::forget();
    }

    /**
     * Create the primary company profile once; every later run is a no-op.
     */
    private function createMainCompany(): void
    {
        $owner = User::query()
            ->where('email', 'info@aquashieldrestorationusa.com')
            ->firstOrFail();

        if (CompanyData::query()->exists()) {
            return;
        }

        $company = new CompanyData;

        $company->fill([
            'name' => 'Victor Lara',
            'company_name' => 'AquaShield Restoration USA',
            'description' => 'Water damage restoration, mold remediation, and reconstruction services in Houston, Texas.',
            'email' => 'info@aquashieldrestorationusa.com',
            'phone' => '+17135876423',
            'address' => '3733 Westheimer Rd. Ste 1-4583, Houston, TX 77027',
            'country' => 'United States',
            'country_code' => 'US',
            'website' => 'https://aquashieldrestorationusa.com',
            'latitude' => 29.75516,
            'longitude' => -95.3984135,
            'facebook_link' => 'https://www.facebook.com/aquashieldrestorationusa/',
            'instagram_link' => 'https://www.instagram.com/aquashieldrestorationusa/',
            'linkedin_link' => 'https://www.linkedin.com/company/aquashieldrestorationusa/',
            'twitter_link' => 'https://twitter.com/aquashieldrestorationusa',
        ]);

        // Ownership, signature and branding assets are set explicitly: none of
        // them is mass-assignable from the profile endpoint. `img/...` tells
        // CompanyProfile to serve the bundled public asset; an R2 object key
        // would be used verbatim after a real upload.
        $company->user_id = $owner->id;
        $company->signature_path = '/signatures/victor_lara_signature.png';
        $company->logo_path = 'https://pub-92ad7c0f92a442a485c4eab0514a2faa.r2.dev/logos/logo.webp';
        $company->logo_white_path = 'https://pub-92ad7c0f92a442a485c4eab0514a2faa.r2.dev/logos/logo-white.webp';
        $company->mark_path = 'https://pub-92ad7c0f92a442a485c4eab0514a2faa.r2.dev/logos/logo-mark.webp';
        $company->save();
    }
}
