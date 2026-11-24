<?php

declare(strict_types=1);

namespace Modules\LeadAppointment\Application\Dtos;

use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

#[MapInputName(SnakeCaseMapper::class)]
final class SaveAvailabilityRuleData extends Data
{
    public function __construct(
        public int $dayOfWeek,
        public string $startTime,
        public string $endTime,
        public bool $isAvailable = true,
    ) {}
}
