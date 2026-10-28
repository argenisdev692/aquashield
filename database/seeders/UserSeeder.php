<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Seeder for application users and initial role assignments.
 *
 * Implements idempotent firstOrCreate logic for all users.
 */
class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $users = [
            // SUPER ADMINS
            [
                'first_name' => 'Victor Lara',
                'username' => 'victor.lara',
                'email' => 'info@aquashieldrestorationusa.com',
                'password' => Hash::make('info01='),
                'role' => 'SUPER_ADMIN',
            ],
            [
                'first_name' => 'Argenis Gonzalez',
                'username' => 'argenis.gonzalez',
                'email' => 'argenis692@gmail.com',
                'password' => Hash::make('argenis01='),
                'role' => 'SUPER_ADMIN',
            ],

            // CORE & OPERATIONAL USERS
            [
                'first_name' => 'Administrator',
                'username' => 'admin.accounts',
                'email' => 'admin@aquashieldrestorationusa.com',
                'password' => Hash::make('admin01='),
                'role' => 'ADMIN',
            ],
            [
                'first_name' => 'Manager',
                'username' => 'manager.ops',
                'email' => 'manager@aquashieldrestorationusa.com',
                'password' => Hash::make('manager01='),
                'role' => 'MANAGER',
            ],
            [
                'first_name' => 'User',
                'username' => 'staff.ops',
                'email' => 'user@aquashieldrestorationusa.com',
                'password' => Hash::make('user01='),
                'role' => 'USER',
            ],
            [
                'first_name' => 'Marketing Manager',
                'username' => 'brand.marketing',
                'email' => 'marketingmanager@aquashieldrestorationusa.com',
                'password' => Hash::make('marketingmanager01='),
                'role' => 'MARKETING_MANAGER',
            ],
            [
                'first_name' => 'Director Assistant',
                'username' => 'director.assistant',
                'email' => 'directorassistant@aquashieldrestorationusa.com',
                'password' => Hash::make('directorassistant01='),
                'role' => 'DIRECTOR_ASSISTANT',
            ],
            [
                'first_name' => 'Technical Supervisor',
                'username' => 'technical.supervisor',
                'email' => 'technicalsupervisor@aquashieldrestorationusa.com',
                'password' => Hash::make('technicalsupervisor01='),
                'role' => 'TECHNICAL_SUPERVISOR',
            ],
            [
                'first_name' => 'Representation Company',
                'username' => 'representation.co',
                'email' => 'representationcompany@aquashieldrestorationusa.com',
                'password' => Hash::make('representationcompany01='),
                'role' => 'REPRESENTATION_COMPANY',
            ],
            [
                'first_name' => 'Public Company',
                'username' => 'company.public',
                'email' => 'publiccompany@aquashieldrestorationusa.com',
                'password' => Hash::make('publiccompany01='),
                'role' => 'PUBLIC_COMPANY',
            ],
            [
                'first_name' => 'External Operators',
                'username' => 'external.operators',
                'email' => 'externaloperators@aquashieldrestorationusa.com',
                'password' => Hash::make('externaloperators01='),
                'role' => 'EXTERNAL_OPERATORS',
            ],
            [
                'first_name' => 'Public Adjuster',
                'username' => 'claims.adjuster',
                'email' => 'publicadjuster@aquashieldrestorationusa.com',
                'password' => Hash::make('publicadjuster01='),
                'role' => 'PUBLIC_ADJUSTER',
            ],
            [
                'first_name' => 'Insurance Adjuster',
                'username' => 'insurance.adjuster',
                'email' => 'insuranceadjuster@aquashieldrestorationusa.com',
                'password' => Hash::make('insuranceadjuster01='),
                'role' => 'INSURANCE_ADJUSTER',
            ],
            [
                'first_name' => 'Technical Services',
                'username' => 'technical.services',
                'email' => 'technicalservices@aquashieldrestorationusa.com',
                'password' => Hash::make('technicalservices01='),
                'role' => 'TECHNICAL_SERVICES',
            ],
            [
                'first_name' => 'Marketing',
                'username' => 'marketing.team',
                'email' => 'marketing@aquashieldrestorationusa.com',
                'password' => Hash::make('marketing01='),
                'role' => 'MARKETING',
            ],
            [
                'first_name' => 'Warehouse',
                'username' => 'warehouse.ops',
                'email' => 'warehouse@aquashieldrestorationusa.com',
                'password' => Hash::make('warehouse01='),
                'role' => 'WAREHOUSE',
            ],
            [
                'first_name' => 'Administrative',
                'username' => 'administrative',
                'email' => 'administrative@aquashieldrestorationusa.com',
                'password' => Hash::make('administrative01='),
                'role' => 'ADMINISTRATIVE',
            ],
            [
                'first_name' => 'Collections',
                'username' => 'collections',
                'email' => 'collections@aquashieldrestorationusa.com',
                'password' => Hash::make('collections01='),
                'role' => 'COLLECTIONS',
            ],
            [
                'first_name' => 'Reportes',
                'username' => 'reportes',
                'email' => 'reportes@aquashieldrestorationusa.com',
                'password' => Hash::make('reportes01='),
                'role' => 'REPORTES',
            ],
            [
                'first_name' => 'Salesperson',
                'username' => 'salesperson',
                'email' => 'salesperson@aquashieldrestorationusa.com',
                'password' => Hash::make('salesperson01='),
                'role' => 'SALESPERSON',
            ],
            [
                'first_name' => 'Lead',
                'username' => 'lead',
                'email' => 'lead@aquashieldrestorationusa.com',
                'password' => Hash::make('lead01='),
                'role' => 'LEAD',
            ],
            [
                'first_name' => 'Employees',
                'username' => 'employees',
                'email' => 'employees@aquashieldrestorationusa.com',
                'password' => Hash::make('employees01='),
                'role' => 'EMPLOYEES',
            ],
            [
                'first_name' => 'Client',
                'username' => 'client',
                'email' => 'client@aquashieldrestorationusa.com',
                'password' => Hash::make('client01='),
                'role' => 'CLIENT',
            ],
            [
                'first_name' => 'Contact',
                'username' => 'contact.ops',
                'email' => 'contact@aquashieldrestorationusa.com',
                'password' => Hash::make('contact01='),
                'role' => 'CONTACT',
            ],
            [
                'first_name' => 'Spectator',
                'username' => 'spectator',
                'email' => 'spectator@aquashieldrestorationusa.com',
                'password' => Hash::make('spectator01='),
                'role' => 'SPECTATOR',
            ],

            // LOCAL DEMO / TEST ACCOUNT
            [
                'first_name' => 'AquaShield Demo',
                'username' => 'demo.account',
                'email' => 'demo@aquashield.test',
                'password' => Hash::make('demo01='),
                'role' => 'SUPER_ADMIN',
            ],
        ];

        foreach ($users as $userData) {
            $role = $userData['role'];
            unset($userData['role']);

            $user = User::firstOrCreate(
                ['email' => $userData['email']],
                [
                    ...$userData,
                    'uuid' => (string) Str::uuid7(),
                    'email_verified_at' => Carbon::now(),
                    'registered_by_id' => 1,
                ],
            );

            if (empty($user->uuid)) {
                $user->update([
                    'uuid' => (string) Str::uuid7(),
                ]);
            }

            // firstOrCreate is idempotent, so it only fills these on insert.
            // Backfill the two fields that make a seeded account usable:
            // a stable username and a verified address. Existing non-null
            // values are never overwritten.
            if (empty($user->username)) {
                $user->update(['username' => $userData['username']]);
            }

            if ($user->registered_by_id === null) {
                $user->forceFill(['registered_by_id' => 1])->save();
            }

            if (! $user->hasVerifiedEmail()) {
                $user->markEmailAsVerified();
            }

            $user->load('roles', 'permissions');
            $user->syncRoles([$role]);
        }
    }
}
