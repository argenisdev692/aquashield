<?php

declare(strict_types=1);

namespace Modules\CompanyData\Actions;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Modules\CompanyData\Models\CompanyData;
use Shared\Domain\Ports\CompanyBrandingPort;

/**
 * Creates the singleton company profile once and returns the existing row on
 * later calls.
 *
 * Shared by the demo seeder and the production `company-data:provision`
 * command so the bootstrap data and the asset defaults cannot drift.
 */
final readonly class ProvisionCompanyDataAction
{
    public function __construct(
        private CompanyBrandingPort $branding,
    ) {}

    public function execute(User $owner): CompanyData
    {
        return DB::transaction(function () use ($owner): CompanyData {
            $existing = CompanyData::query()
                ->oldest('id')
                ->lockForUpdate()
                ->first();

            if ($existing !== null) {
                return $existing;
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

            // Ownership and branding assets stay outside the fill allowlist:
            // a profile PUT must never point them at an attacker-chosen value.
            $company->user_id = $owner->id;
            $company->logo_path = 'img/logo.webp';
            $company->logo_white_path = 'img/logo-white.webp';
            $company->mark_path = 'img/logo-mark.webp';
            $company->save();

            // The seeder runs with model events disabled; flush explicitly so
            // the welcome/email payload never serves the pre-seed fallback.
            $this->branding->forget();

            return $company;
        });
    }
}
