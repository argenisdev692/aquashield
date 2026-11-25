<?php

declare(strict_types=1);

namespace Modules\LeadAppointment\Application\Dtos;

use Spatie\LaravelData\Attributes\MapOutputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

/**
 * The dashboard calendar's projection of one visit.
 *
 * The month grid needs the customer name, the wall-clock start and the state
 * that decides the event tone. It does not need notes, contact details,
 * addresses or Google event ids; those belong to the appointment file and are
 * read on demand. Keeping this shape separate from `AppointmentData` is what
 * stops the dashboard from shipping a month of PII it never renders.
 */
#[MapOutputName(SnakeCaseMapper::class)]
final class CalendarEventData extends Data
{
    public function __construct(
        public string $uuid,
        public string $leadUuid,
        public string $customerName,
        public string $startsAtLocal,
        public string $timezone,
        public string $status,
        public string $kind,
        public string $region,
    ) {}
}
