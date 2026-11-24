<?php

declare(strict_types=1);

namespace Modules\LeadAppointment\Infrastructure\Persistence\Eloquent\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\LeadAppointment\Domain\Enums\AvailabilitySource;
use Modules\LeadAppointment\Infrastructure\Persistence\Eloquent\Models\AvailabilityException;

/**
 * @extends Factory<AvailabilityException>
 */
final class AvailabilityExceptionFactory extends Factory
{
    protected $model = AvailabilityException::class;

    public function definition(): array
    {
        return [
            'date' => now()->addDay()->toDateString(),
            'is_available' => false,
            'start_time' => null,
            'end_time' => null,
            'reason' => 'Company closure',
            'source' => AvailabilitySource::Manual->value,
        ];
    }
}
