<?php

declare(strict_types=1);

namespace Modules\LeadAppointment\Application\Dtos;

use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

/**
 * Bounded date window for the lead-source report.
 *
 * The report is an aggregate over the lead table, so an unbounded range is a
 * resource-consumption risk. The controller enforces the maximum span; this DTO
 * only owns the wire contract and the format rules.
 */
#[MapInputName(SnakeCaseMapper::class)]
final class LeadSourceReportFilterData extends Data
{
    public function __construct(
        public ?string $from = null,
        public ?string $to = null,
    ) {}

    /**
     * @return array<string, array<int, string>>
     */
    public static function rules(): array
    {
        return [
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
        ];
    }
}
