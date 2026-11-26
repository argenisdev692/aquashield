<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     *
     * One transaction: a failing demo/reference seeder must not leave a
     * partially seeded install (roles present, no super admin, no company).
     */
    public function run(): void
    {
        DB::transaction(function (): void {
            $this->call([
                RolePermissionSeeder::class,
                UserSeeder::class,
                CompanyDataSeeder::class,
                LeadAppointmentSeeder::class,
            ]);
        });
    }
}
