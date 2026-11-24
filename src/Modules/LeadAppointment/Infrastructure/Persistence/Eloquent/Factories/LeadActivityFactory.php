<?php

declare(strict_types=1);

namespace Modules\LeadAppointment\Infrastructure\Persistence\Eloquent\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\LeadAppointment\Domain\Enums\LeadActivityType;
use Modules\LeadAppointment\Infrastructure\Persistence\Eloquent\Models\Lead;
use Modules\LeadAppointment\Infrastructure\Persistence\Eloquent\Models\LeadActivity;

/**
 * @extends Factory<LeadActivity>
 */
final class LeadActivityFactory extends Factory
{
    protected $model = LeadActivity::class;

    public function definition(): array
    {
        return [
            'lead_id' => Lead::factory(),
            'type' => LeadActivityType::Call->value,
            'outcome' => 'connected',
            'notes' => fake()->sentence(),
            'occurred_at' => now(),
        ];
    }
}
