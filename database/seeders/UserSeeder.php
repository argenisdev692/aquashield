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
                'name' => 'Victor Lara',
                'email' => 'info@aquashieldrestorationusa.com',
                'password' => Hash::make('info01='),
                'role' => 'SUPER_ADMIN',
            ],
            [
                'name' => 'Argenis Gonzalez',
                'email' => 'argenis692@gmail.com',
                'password' => Hash::make('argenis01='),
                'role' => 'SUPER_ADMIN',
            ],

            // CORE & OPERATIONAL USERS
            [
                'name' => 'Administrator',
                'email' => 'admin@aquashieldrestorationusa.com',
                'password' => Hash::make('admin01='),
                'role' => 'ADMIN',
            ],
            [
                'name' => 'Manager',
                'email' => 'manager@aquashieldrestorationusa.com',
                'password' => Hash::make('manager01='),
                'role' => 'MANAGER',
            ],
            [
                'name' => 'User',
                'email' => 'user@aquashieldrestorationusa.com',
                'password' => Hash::make('user01='),
                'role' => 'USER',
            ],
            [
                'name' => 'Marketing Manager',
                'email' => 'marketingmanager@aquashieldrestorationusa.com',
                'password' => Hash::make('marketingmanager01='),
                'role' => 'MARKETING_MANAGER',
            ],
            [
                'name' => 'Director Assistant',
                'email' => 'directorassistant@aquashieldrestorationusa.com',
                'password' => Hash::make('directorassistant01='),
                'role' => 'DIRECTOR_ASSISTANT',
            ],
            [
                'name' => 'Technical Supervisor',
                'email' => 'technicalsupervisor@aquashieldrestorationusa.com',
                'password' => Hash::make('technicalsupervisor01='),
                'role' => 'TECHNICAL_SUPERVISOR',
            ],
            [
                'name' => 'Representation Company',
                'email' => 'representationcompany@aquashieldrestorationusa.com',
                'password' => Hash::make('representationcompany01='),
                'role' => 'REPRESENTATION_COMPANY',
            ],
            [
                'name' => 'Public Company',
                'email' => 'publiccompany@aquashieldrestorationusa.com',
                'password' => Hash::make('publiccompany01='),
                'role' => 'PUBLIC_COMPANY',
            ],
            [
                'name' => 'External Operators',
                'email' => 'externaloperators@aquashieldrestorationusa.com',
                'password' => Hash::make('externaloperators01='),
                'role' => 'EXTERNAL_OPERATORS',
            ],
            [
                'name' => 'Public Adjuster',
                'email' => 'publicadjuster@aquashieldrestorationusa.com',
                'password' => Hash::make('publicadjuster01='),
                'role' => 'PUBLIC_ADJUSTER',
            ],
            [
                'name' => 'Insurance Adjuster',
                'email' => 'insuranceadjuster@aquashieldrestorationusa.com',
                'password' => Hash::make('insuranceadjuster01='),
                'role' => 'INSURANCE_ADJUSTER',
            ],
            [
                'name' => 'Technical Services',
                'email' => 'technicalservices@aquashieldrestorationusa.com',
                'password' => Hash::make('technicalservices01='),
                'role' => 'TECHNICAL_SERVICES',
            ],
            [
                'name' => 'Marketing',
                'email' => 'marketing@aquashieldrestorationusa.com',
                'password' => Hash::make('marketing01='),
                'role' => 'MARKETING',
            ],
            [
                'name' => 'Warehouse',
                'email' => 'warehouse@aquashieldrestorationusa.com',
                'password' => Hash::make('warehouse01='),
                'role' => 'WAREHOUSE',
            ],
            [
                'name' => 'Administrative',
                'email' => 'administrative@aquashieldrestorationusa.com',
                'password' => Hash::make('administrative01='),
                'role' => 'ADMINISTRATIVE',
            ],
            [
                'name' => 'Collections',
                'email' => 'collections@aquashieldrestorationusa.com',
                'password' => Hash::make('collections01='),
                'role' => 'COLLECTIONS',
            ],
            [
                'name' => 'Reportes',
                'email' => 'reportes@aquashieldrestorationusa.com',
                'password' => Hash::make('reportes01='),
                'role' => 'REPORTES',
            ],
            [
                'name' => 'Salesperson',
                'email' => 'salesperson@aquashieldrestorationusa.com',
                'password' => Hash::make('salesperson01='),
                'role' => 'SALESPERSON',
            ],
            [
                'name' => 'Lead',
                'email' => 'lead@aquashieldrestorationusa.com',
                'password' => Hash::make('lead01='),
                'role' => 'LEAD',
            ],
            [
                'name' => 'Employees',
                'email' => 'employees@aquashieldrestorationusa.com',
                'password' => Hash::make('employees01='),
                'role' => 'EMPLOYEES',
            ],
            [
                'name' => 'Client',
                'email' => 'client@aquashieldrestorationusa.com',
                'password' => Hash::make('client01='),
                'role' => 'CLIENT',
            ],
            [
                'name' => 'Contact',
                'email' => 'contact@aquashieldrestorationusa.com',
                'password' => Hash::make('contact01='),
                'role' => 'CONTACT',
            ],
            [
                'name' => 'Spectator',
                'email' => 'spectator@aquashieldrestorationusa.com',
                'password' => Hash::make('spectator01='),
                'role' => 'SPECTATOR',
            ],

            // LOCAL DEMO / TEST ACCOUNT
            [
                'name' => 'AquaShield Demo',
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
                ],
            );

            if (empty($user->uuid)) {
                $user->update([
                    'uuid' => (string) Str::uuid7(),
                ]);
            }

            $user->load('roles', 'permissions');
            $user->syncRoles([$role]);
        }
    }
}
