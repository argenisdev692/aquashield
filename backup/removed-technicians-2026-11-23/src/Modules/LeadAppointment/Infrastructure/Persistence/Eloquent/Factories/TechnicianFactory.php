<?php

declare(strict_types=1);

namespace Modules\LeadAppointment\Infrastructure\Persistence\Eloquent\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\LeadAppointment\Domain\Enums\Region;
use Modules\LeadAppointment\Infrastructure\Persistence\Eloquent\Models\Technician;

/**
 * @extends Factory<Technician>
 */
final class TechnicianFactory extends Factory
{
    protected $model = Technician::class;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'region' => Region::Houston->value,
            'phone' => '+1'.fake()->numerify('##########'),
            'email' => fake()->unique()->safeEmail(),
            'is_active' => true,
            'daily_capacity' => 4,
            'travel_buffer_minutes' => 30,
        ];
    }
}
