<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * The demo sign-in for testing, wired into DatabaseSeeder's call list.
 *
 * One user, deterministic credentials (`demo@aquashield.test` / `password`),
 * so the auth flow can be exercised by hand right after seeding. Idempotent
 * — `firstOrCreate` — because re-seeding an existing local database must
 * never 500 over a duplicate email.
 */
final class UserSeeder extends Seeder
{
    public function run(): void
    {
        User::firstOrCreate(
            ['email' => 'demo@aquashield.test'],
            [
                'name' => 'AquaShield Demo',
                'password' => 'password',
                'email_verified_at' => Carbon::now(),
            ],
        );
    }
}
