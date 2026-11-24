<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Modules\LeadAppointment\Infrastructure\Persistence\Eloquent\Models\AvailabilityRule;

/**
 * Reference data the module cannot function without: the company's default
 * working hours. Inspectors are `users` with the TECHNICAL_SERVICES role when
 * the Claim + Scope Sheet phase assigns them; there is no technicians table to
 * seed here.
 */
final class LeadAppointmentSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            // Company default: Monday–Friday 08:00–17:00, Saturday 08:00–14:00.
            // Sunday is intentionally absent (closed).
            foreach ([1, 2, 3, 4, 5] as $day) {
                $this->rule($day, '08:00', '17:00');
            }

            $this->rule(6, '08:00', '14:00');
        });
    }

    private function rule(int $dayOfWeek, string $start, string $end): void
    {
        AvailabilityRule::query()->updateOrCreate(
            [
                'day_of_week' => $dayOfWeek,
                'start_time' => $start,
                'end_time' => $end,
            ],
            ['is_available' => true],
        );
    }
}
