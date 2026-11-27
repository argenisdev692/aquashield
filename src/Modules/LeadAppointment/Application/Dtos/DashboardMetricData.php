<?php

declare(strict_types=1);

namespace Modules\LeadAppointment\Application\Dtos;

use Spatie\LaravelData\Attributes\MapOutputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

#[MapOutputName(SnakeCaseMapper::class)]
final class DashboardMetricData extends Data
{
    /**
     * @param list<int> $series Daily counts for the current window, one entry per day.
     */
    public function __construct(
        public int $current,
        public int $previous,
        public array $series = [],
    ) {}
}
