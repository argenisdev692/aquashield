<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Shared\Domain\Enums\Role as RoleName;

/**
 * Local/testing demo accounts.
 *
 * This seeder is **not** a production provisioning path:
 *
 * - it returns immediately outside `local` and `testing`;
 * - it requires `SEED_USER_PASSWORD` (no committed default);
 * - it never resets the password or the roles of an existing account;
 * - it never resurrects a soft-deleted account.
 *
 * Production administrators are created explicitly with a secret-managed
 * credential and then onboarded through the normal flow. Keeping demo users
 * out of the repository and out of shared environments is the point.
 */
final class UserSeeder extends Seeder
{
    /** @var list<array{first_name: string, username: string, email: string, role: string}> */
    private const array USERS = [
        // SUPER ADMINS
        [
            'first_name' => 'Victor Lara',
            'username' => 'victor.lara',
            'email' => 'info@aquashieldrestorationusa.com',
            'role' => RoleName::SuperAdmin->value,
        ],
        [
            'first_name' => 'Argenis Gonzalez',
            'username' => 'argenis.gonzalez',
            'email' => 'argenis692@gmail.com',
            'role' => RoleName::SuperAdmin->value,
        ],

        // CORE & OPERATIONAL USERS
        [
            'first_name' => 'Administrator',
            'username' => 'admin.accounts',
            'email' => 'admin@aquashieldrestorationusa.com',
            'role' => RoleName::Admin->value,
        ],
        [
            'first_name' => 'Manager',
            'username' => 'manager.ops',
            'email' => 'manager@aquashieldrestorationusa.com',
            'role' => 'MANAGER',
        ],
        [
            'first_name' => 'User',
            'username' => 'staff.ops',
            'email' => 'user@aquashieldrestorationusa.com',
            'role' => 'USER',
        ],
        [
            'first_name' => 'Marketing Manager',
            'username' => 'brand.marketing',
            'email' => 'marketingmanager@aquashieldrestorationusa.com',
            'role' => 'MARKETING_MANAGER',
        ],
        [
            'first_name' => 'Director Assistant',
            'username' => 'director.assistant',
            'email' => 'directorassistant@aquashieldrestorationusa.com',
            'role' => 'DIRECTOR_ASSISTANT',
        ],
        [
            'first_name' => 'Technical Supervisor',
            'username' => 'technical.supervisor',
            'email' => 'technicalsupervisor@aquashieldrestorationusa.com',
            'role' => 'TECHNICAL_SUPERVISOR',
        ],
        [
            'first_name' => 'Representation Company',
            'username' => 'representation.co',
            'email' => 'representationcompany@aquashieldrestorationusa.com',
            'role' => 'REPRESENTATION_COMPANY',
        ],
        [
            'first_name' => 'Public Company',
            'username' => 'company.public',
            'email' => 'publiccompany@aquashieldrestorationusa.com',
            'role' => 'PUBLIC_COMPANY',
        ],
        [
            'first_name' => 'External Operators',
            'username' => 'external.operators',
            'email' => 'externaloperators@aquashieldrestorationusa.com',
            'role' => 'EXTERNAL_OPERATORS',
        ],
        [
            'first_name' => 'Public Adjuster',
            'username' => 'claims.adjuster',
            'email' => 'publicadjuster@aquashieldrestorationusa.com',
            'role' => 'PUBLIC_ADJUSTER',
        ],
        [
            'first_name' => 'Insurance Adjuster',
            'username' => 'insurance.adjuster',
            'email' => 'insuranceadjuster@aquashieldrestorationusa.com',
            'role' => 'INSURANCE_ADJUSTER',
        ],
        [
            'first_name' => 'Technical Services',
            'username' => 'technical.services',
            'email' => 'technicalservices@aquashieldrestorationusa.com',
            'role' => 'TECHNICAL_SERVICES',
        ],
        [
            'first_name' => 'Marketing',
            'username' => 'marketing.team',
            'email' => 'marketing@aquashieldrestorationusa.com',
            'role' => 'MARKETING',
        ],
        [
            'first_name' => 'Warehouse',
            'username' => 'warehouse.ops',
            'email' => 'warehouse@aquashieldrestorationusa.com',
            'role' => 'WAREHOUSE',
        ],
        [
            'first_name' => 'Administrative',
            'username' => 'administrative',
            'email' => 'administrative@aquashieldrestorationusa.com',
            'role' => 'ADMINISTRATIVE',
        ],
        [
            'first_name' => 'Collections',
            'username' => 'collections',
            'email' => 'collections@aquashieldrestorationusa.com',
            'role' => 'COLLECTIONS',
        ],
        [
            'first_name' => 'Reportes',
            'username' => 'reportes',
            'email' => 'reportes@aquashieldrestorationusa.com',
            'role' => 'REPORTES',
        ],
        [
            'first_name' => 'Salesperson',
            'username' => 'salesperson',
            'email' => 'salesperson@aquashieldrestorationusa.com',
            'role' => 'SALESPERSON',
        ],
        [
            'first_name' => 'Lead',
            'username' => 'lead',
            'email' => 'lead@aquashieldrestorationusa.com',
            'role' => 'LEAD',
        ],
        [
            'first_name' => 'Employees',
            'username' => 'employees',
            'email' => 'employees@aquashieldrestorationusa.com',
            'role' => 'EMPLOYEES',
        ],
        [
            'first_name' => 'Client',
            'username' => 'client',
            'email' => 'client@aquashieldrestorationusa.com',
            'role' => 'CLIENT',
        ],
        [
            'first_name' => 'Contact',
            'username' => 'contact.ops',
            'email' => 'contact@aquashieldrestorationusa.com',
            'role' => 'CONTACT',
        ],
        [
            'first_name' => 'Spectator',
            'username' => 'spectator',
            'email' => 'spectator@aquashieldrestorationusa.com',
            'role' => 'SPECTATOR',
        ],

        // LOCAL DEMO / TEST ACCOUNT
        [
            'first_name' => 'AquaShield Demo',
            'username' => 'demo.account',
            'email' => 'demo@aquashield.test',
            'role' => RoleName::SuperAdmin->value,
        ],
    ];

    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            $this->command?->warn('UserSeeder is local/testing only; no demo users were created.');

            return;
        }

        $password = (string) config('seed.user_password', '');

        if ($password === '') {
            $this->command?->warn('SEED_USER_PASSWORD is empty; skipping demo users. Set it in .env to seed them.');

            return;
        }

        // One shared local password: the accounts are demo data, not a
        // production credential set. Hashing once avoids 26 Argon2 runs.
        $passwordHash = Hash::make($password);

        foreach (self::USERS as $userData) {
            $role = $userData['role'];
            unset($userData['role']);

            $user = User::withTrashed()->where('email', $userData['email'])->first();
            $created = $user === null;

            if ($created) {
                $user = User::query()->create([
                    ...$userData,
                    'password' => $passwordHash,
                    'uuid' => (string) Str::uuid7(),
                    'email_verified_at' => Carbon::now(),
                ]);
            }

            // A trashed account must not be resurrected or re-roled by a seed.
            if ($user->trashed()) {
                continue;
            }

            if (empty($user->uuid)) {
                $user->update(['uuid' => (string) Str::uuid7()]);
            }

            if (empty($user->username)) {
                $user->update(['username' => $userData['username']]);
            }

            if (! $user->hasVerifiedEmail()) {
                $user->markEmailAsVerified();
            }

            /*
             * Never touch an existing account's matrix — not even to fill an
             * empty one. Only the account this run just created receives the
             * documented demo role.
             */
            if ($created) {
                $user->assignRole($role);
            }
        }
    }
}
