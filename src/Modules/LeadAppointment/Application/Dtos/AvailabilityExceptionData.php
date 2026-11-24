<?php

declare(strict_types=1);

namespace Modules\LeadAppointment\Application\Dtos;

use Spatie\LaravelData\Attributes\MapOutputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

#[MapOutputName(SnakeCaseMapper::class)]
final class AvailabilityExceptionData extends Data
{
    public function __construct(
        public string $uuid,
        public string $date,
        public bool $isAvailable,
        public ?string $startTime,
        public ?string $endTime,
        public ?string $reason,
        public string $source,
    ) {}
}
