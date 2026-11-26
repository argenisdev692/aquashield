<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Modules\CompanyData\Actions\ProvisionCompanyDataAction;
use Modules\CompanyData\Models\CompanyData;

/**
 * Seeds the CompanyData singleton (AquaShield Restoration USA) for local demo
 * installations. Production provisioning uses `company-data:provision`.
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
     * Create the primary company profile once; every later run is a no-op.
     */
    private function createMainCompany(): void
    {
        if (CompanyData::query()->exists()) {
            return;
        }

        $owner = User::query()
            ->where('email', 'info@aquashieldrestorationusa.com')
            ->first();

        if (! $owner instanceof User) {
            $this->command?->warn(
                'CompanyDataSeeder: no user with email info@aquashieldrestorationusa.com exists; skipping the company profile.',
            );

            return;
        }

        app(ProvisionCompanyDataAction::class)->execute($owner);
    }
}
