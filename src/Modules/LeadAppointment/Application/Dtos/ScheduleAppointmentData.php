<?php

declare(strict_types=1);

namespace Modules\LeadAppointment\Application\Dtos;

use Modules\LeadAppointment\Domain\Enums\AppointmentKind;
use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

#[MapInputName(SnakeCaseMapper::class)]
final class ScheduleAppointmentData extends Data
{
    public function __construct(
        public string $leadUuid,
        public AppointmentKind $kind = AppointmentKind::Inspection,
        public ?string $startsAt = null,
        public ?int $durationMinutes = null,
        public ?string $timezone = null,
        public ?string $region = null,
        public ?string $notes = null,
        public bool $isEmergency = false,
    ) {}
}
