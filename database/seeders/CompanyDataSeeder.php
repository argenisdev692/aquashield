<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Seeder;
use Modules\CompanyData\Actions\ProvisionCompanyDataAction;
use Modules\CompanyData\Models\CompanyData;
use Modules\Users\Enums\UserStatus;
use Shared\Domain\Enums\Role as RoleName;

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

        $configuredEmail = trim((string) config('seed.company_owner_email', ''))
            ?: UserSeeder::DEMO_OWNER_EMAIL;

        $owner = User::query()->where('email', $configuredEmail)->first();

        // An existing install may have a real SUPER_ADMIN under another
        // address. Prefer it over failing the whole seed. `whereHas` avoids
        // Spatie's role scope throwing when the role catalogue is empty.
        $owner ??= User::query()
            ->whereHas('roles', static fn (Builder $query): Builder => $query->where('name', RoleName::SuperAdmin->value))
            ->where('status', UserStatus::Active->value)
            ->first();

        if (! $owner instanceof User) {
            $this->command?->warn(
                "CompanyDataSeeder: no user with email [{$configuredEmail}] and no active SUPER_ADMIN exists; skipping the company profile.",
            );

            return;
        }

        app(ProvisionCompanyDataAction::class)->execute($owner);
    }
}
