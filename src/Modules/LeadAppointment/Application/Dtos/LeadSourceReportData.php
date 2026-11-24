<?php

declare(strict_types=1);

namespace Modules\LeadAppointment\Application\Dtos;

use Spatie\LaravelData\Attributes\MapOutputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

#[MapOutputName(SnakeCaseMapper::class)]
final class LeadSourceReportData extends Data
{
    /**
     * @param list<LeadSourceReportRowData> $rows
     */
    public function __construct(
        public string $from,
        public string $to,
        public array $rows,
        public ?int $averageFirstContactMinutes,
    ) {}
}
