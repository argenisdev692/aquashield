<?php

declare(strict_types=1);

namespace Modules\LeadAppointment\Application\Dtos;

use Spatie\LaravelData\Attributes\MapOutputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

#[MapOutputName(SnakeCaseMapper::class)]
final class LeadSourceReportRowData extends Data
{
    public function __construct(
        public string $source,
        public string $sourceLabel,
        public int $leads,
        public int $appointments,
        public int $won,
        public float $conversionRate,
        public ?int $averageFirstContactMinutes,
    ) {}
}
