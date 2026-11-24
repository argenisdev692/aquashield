<?php

declare(strict_types=1);

namespace Modules\LeadAppointment\Application\Dtos;

use Spatie\LaravelData\Attributes\MapOutputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

#[MapOutputName(SnakeCaseMapper::class)]
final class CalendarFeedData extends Data
{
    /**
     * @param list<CalendarDayData> $days
     */
    public function __construct(
        public string $from,
        public string $to,
        public string $timezone,
        public array $days,
    ) {}
}
