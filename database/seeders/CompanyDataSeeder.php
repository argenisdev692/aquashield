<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\CompanyData;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Seeder for initial company data (AquaShield Restoration USA).
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
    }

    /**
     * Create or retrieve the primary company profile.
     */
    private function createMainCompany(): void
    {
        $superAdminId = User::query()
            ->where('email', 'info@aquashieldrestorationusa.com')
            ->value('id') ?? 1;

        $company = CompanyData::firstOrCreate(
            ['email' => 'info@aquashieldrestorationusa.com'],
            [
                'uuid' => (string) Str::uuid7(),
                'name' => 'Victor Lara',
                'company_name' => 'Aquashield Restoration USA',
                'signature_path' => '/signatures/victor_lara_signature.png',
                'email' => 'info@aquashieldrestorationusa.com',
                'phone' => '+17135876423',
                'address' => '1321 Upland Dr. PMB 4455, Houston, TX 77043',
                'website' => 'https://aquashieldrestorationusa.com',
                'latitude' => 29.75516,
                'longitude' => -95.3984135,
                'user_id' => $superAdminId,
                'facebook_link' => 'https://www.facebook.com/aquashieldrestorationusa/',
                'instagram_link' => 'https://www.instagram.com/aquashieldrestorationusa/',
                'linkedin_link' => 'https://www.linkedin.com/company/aquashieldrestorationusa/',
                'twitter_link' => 'https://twitter.com/aquashieldrestorationusa',
            ],
        );

        if (empty($company->uuid)) {
            $company->update([
                'uuid' => (string) Str::uuid7(),
            ]);
        }
    }
}
