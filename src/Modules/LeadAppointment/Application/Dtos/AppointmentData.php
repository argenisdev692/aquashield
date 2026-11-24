<?php

declare(strict_types=1);

namespace Modules\LeadAppointment\Application\Dtos;

use Spatie\LaravelData\Attributes\MapOutputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

/**
 * Flat appointment payload. The local ISO strings are what the calendar and the
 * emails render; UTC instants remain the source of truth in the database.
 * Technician assignment belongs to the later Claim + Scope Sheet phase.
 */
#[MapOutputName(SnakeCaseMapper::class)]
final class AppointmentData extends Data
{
    public function __construct(
        public string $uuid,
        public string $leadUuid,
        public string $customerName,
        public ?string $customerEmail,
        public ?string $customerPhone,
        public string $addressLine,
        public string $kind,
        public string $status,
        public string $startsAt,
        public string $endsAt,
        public string $startsAtLocal,
        public string $endsAtLocal,
        public string $timezone,
        public string $region,
        public ?string $previousStartsAt,
        public ?string $googleEventId,
        public ?string $notes,
        public ?string $cancelReason,
        public ?string $createdAt,
        public ?string $deletedAt,
    ) {}
}
