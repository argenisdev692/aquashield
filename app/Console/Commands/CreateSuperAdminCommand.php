<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Role;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use Modules\Users\Enums\UserStatus;
use Shared\Domain\Enums\Role as RoleName;
use Shared\Domain\Ports\AuditPort;
use Throwable;

/**
 * Bootstraps the first production administrator.
 *
 * The role catalogue must already exist (RolePermissionSeeder). The account is
 * created active and verified, with `must_change_password` so the temporary
 * secret supplied here is replaced on first sign-in.
 */
final class CreateSuperAdminCommand extends Command
{
    protected $signature = 'app:create-super-admin
        {--email= : Email address of the bootstrap administrator}
        {--first-name=Administrator : First name}
        {--last-name= : Last name}
        {--password= : Temporary password (prefer the interactive prompt)}';

    protected $description = 'Create the first active SUPER_ADMIN account.';

    public function handle(AuditPort $audit): int
    {
        $email = mb_strtolower(trim((string) $this->option('email')));
        $firstName = trim((string) ($this->option('first-name') ?: 'Administrator'));
        $lastName = trim((string) $this->option('last-name'));

        $validator = Validator::make(
            ['email' => $email, 'first_name' => $firstName, 'last_name' => $lastName],
            [
                'email' => ['required', 'email', 'max:255'],
                'first_name' => ['required', 'string', 'max:255'],
                'last_name' => ['nullable', 'string', 'max:255'],
            ],
        );

        if ($validator->fails()) {
            $this->error((string) $validator->errors()->first());

            return self::FAILURE;
        }

        $role = Role::query()
            ->where('name', RoleName::SuperAdmin->value)
            ->where('guard_name', 'web')
            ->first();

        if ($role === null) {
            $this->error('The SUPER_ADMIN role does not exist. Run: php artisan db:seed --class=RolePermissionSeeder');

            return self::FAILURE;
        }

        if (! $role->permissions()->exists()) {
            $this->error('SUPER_ADMIN has no permissions. Run: php artisan db:seed --class=RolePermissionSeeder');

            return self::FAILURE;
        }

        if (User::withTrashed()->where('email', $email)->exists()) {
            $this->error("A user with email [{$email}] already exists.");

            return self::FAILURE;
        }

        $password = $this->resolvePassword();

        if ($password === null) {
            return self::FAILURE;
        }

        $user = DB::transaction(function () use ($email, $firstName, $lastName, $password): User {
            $user = new User;
            $user->forceFill([
                'first_name' => $firstName,
                'last_name' => $lastName === '' ? null : $lastName,
                'email' => $email,
                'password' => $password,
                'status' => UserStatus::Active,
                'email_verified_at' => now(),
                'activated_at' => now(),
                'must_change_password' => true,
                'terms_and_conditions' => true,
            ])->save();

            $user->assignRole(RoleName::SuperAdmin->value);

            return $user;
        }, attempts: 3);

        try {
            $audit->log('user.super_admin_bootstrapped', $user, [
                'role' => RoleName::SuperAdmin->value,
            ], null, 'users');
        } catch (Throwable $exception) {
            // The account is already committed; a failed audit write must not
            // turn a successful bootstrap into a failure. Report it instead.
            report($exception);
        }

        $this->info("SUPER_ADMIN [{$email}] created. The account must change its password on first sign-in.");

        return self::SUCCESS;
    }

    private function resolvePassword(): ?string
    {
        $password = (string) $this->option('password');

        if ($password === '') {
            if (! $this->input->isInteractive()) {
                $this->error('Provide --password when running non-interactively.');

                return null;
            }

            $password = (string) $this->secret('Temporary password (8-15 characters)');
            $confirmation = (string) $this->secret('Confirm temporary password');

            if ($password !== $confirmation) {
                $this->error('The password confirmation does not match.');

                return null;
            }
        }

        $validator = Validator::make(
            ['password' => $password],
            ['password' => ['required', 'string', Password::min(8)->max(15)]],
        );

        if ($validator->fails()) {
            $this->error((string) $validator->errors()->first());

            return null;
        }

        return $password;
    }
}
