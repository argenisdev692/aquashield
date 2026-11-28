<?php

declare(strict_types=1);

namespace Modules\LeadAppointment\Infrastructure\Persistence\Eloquent\Factories;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\LeadAppointment\Domain\Enums\AppointmentKind;
use Modules\LeadAppointment\Domain\Enums\AppointmentStatus;
use Modules\LeadAppointment\Domain\Enums\Region;
use Modules\LeadAppointment\Infrastructure\Persistence\Eloquent\Models\Appointment;
use Modules\LeadAppointment\Infrastructure\Persistence\Eloquent\Models\Lead;

/**
 * @extends Factory<Appointment>
 */
final class AppointmentFactory extends Factory
{
    protected $model = Appointment::class;

    public function definition(): array
    {
        $start = CarbonImmutable::now()->addDays(2)->setTime(10, 0);
        $end = $start->addHour();

        return [
            'lead_id' => Lead::factory(),
            'kind' => AppointmentKind::Inspection->value,
            'status' => AppointmentStatus::Scheduled->value,
            'is_emergency' => false,
            'starts_at' => $start,
            'ends_at' => $end,
            'timezone' => Region::Houston->timezone(),
            'region' => Region::Houston->value,
            'notes' => null,
        ];
    }
}
