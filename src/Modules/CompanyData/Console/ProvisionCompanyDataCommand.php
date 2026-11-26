<?php

declare(strict_types=1);

namespace Modules\CompanyData\Console;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Database\UniqueConstraintViolationException;
use Modules\CompanyData\Actions\ProvisionCompanyDataAction;
use Modules\CompanyData\Models\CompanyData;
use Modules\Users\Enums\UserStatus;
use Shared\Domain\Enums\Role as RoleName;

/**
 * Production-safe singleton provisioning.
 *
 * The demo seeder is local/testing only and intentionally depends on the demo
 * owner. This command is the deployment path: it resolves an active
 * SUPER_ADMIN (or an explicitly named one) and creates the profile once.
 */
final class ProvisionCompanyDataCommand extends Command
{
    protected $signature = 'company-data:provision
        {--owner= : Email of the active SUPER_ADMIN who will own the singleton}';

    protected $description = 'Create the CompanyData singleton if it does not exist.';

    public function handle(ProvisionCompanyDataAction $action): int
    {
        if (CompanyData::query()->exists()) {
            $this->info('Company profile is already provisioned.');

            return self::SUCCESS;
        }

        $owner = $this->resolveOwner();

        if ($owner === null) {
            return self::FAILURE;
        }

        try {
            $company = $action->execute($owner);
        } catch (UniqueConstraintViolationException) {
            // Two operators ran the command at once; the singleton already
            // exists. Re-read instead of surfacing a race as a failure.
            $company = CompanyData::query()->oldest('id')->firstOrFail();
        }

        $this->info("Company profile provisioned for {$owner->email} ({$company->uuid}).");

        return self::SUCCESS;
    }

    private function resolveOwner(): ?User
    {
        $email = $this->option('owner');
        $owner = is_string($email) && trim($email) !== ''
            ? User::query()->where('email', mb_strtolower(trim($email)))->first()
            : User::query()
                ->role(RoleName::SuperAdmin->value)
                ->where('status', UserStatus::Active->value)
                ->first();

        if (! $owner instanceof User) {
            $this->error(
                'No matching user found. Create an active SUPER_ADMIN first: '
                .'php artisan app:create-super-admin --email=... --password=...',
            );

            return null;
        }

        if (! $owner->isActive() || ! $owner->hasRole(RoleName::SuperAdmin->value)) {
            $this->error("User [{$owner->email}] must be an active SUPER_ADMIN to own the company profile.");

            return null;
        }

        return $owner;
    }
}
