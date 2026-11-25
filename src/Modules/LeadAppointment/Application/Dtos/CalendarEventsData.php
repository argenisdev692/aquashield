<?php

declare(strict_types=1);

namespace Modules\LeadAppointment\Application\Dtos;

use Spatie\LaravelData\Attributes\MapOutputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

/**
 * Bounded calendar-events envelope. The frontend validates the envelope and
 * every row before it renders anything, so a contract drift fails visibly
 * instead of silently shrinking the month.
 */
#[MapOutputName(SnakeCaseMapper::class)]
final class CalendarEventsData extends Data
{
    /**
     * @param list<CalendarEventData> $data
     */
    public function __construct(
        public array $data,
    ) {}
}
