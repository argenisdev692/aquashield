<?php

declare(strict_types=1);

namespace Modules\LeadAppointment\Application\Dtos;

use Spatie\LaravelData\Attributes\MapOutputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

#[MapOutputName(SnakeCaseMapper::class)]
final class AvailabilityCalendarData extends Data
{
    /**
     * @param list<AvailabilityDayData> $data
     */
    public function __construct(public array $data) {}
}
