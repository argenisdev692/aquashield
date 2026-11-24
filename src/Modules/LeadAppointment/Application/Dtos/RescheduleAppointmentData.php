<?php

declare(strict_types=1);

namespace Modules\LeadAppointment\Application\Dtos;

use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

#[MapInputName(SnakeCaseMapper::class)]
final class RescheduleAppointmentData extends Data
{
    public function __construct(
        public string $startsAt,
        public ?int $durationMinutes = null,
        public ?string $timezone = null,
        public string $reason = 'Rescheduled by staff',
    ) {}
}
