<?php

declare(strict_types=1);

namespace Modules\LeadAppointment\Infrastructure\Persistence\Eloquent\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\LeadAppointment\Infrastructure\Persistence\Eloquent\Models\AvailabilityRule;

/**
 * @extends Factory<AvailabilityRule>
 */
final class AvailabilityRuleFactory extends Factory
{
    protected $model = AvailabilityRule::class;

    public function definition(): array
    {
        return [
            'day_of_week' => 1,
            'start_time' => '08:00',
            'end_time' => '17:00',
            'is_available' => true,
        ];
    }
}
