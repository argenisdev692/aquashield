<?php

declare(strict_types=1);

namespace Modules\LeadAppointment\Application\Dtos;

use Modules\LeadAppointment\Domain\Enums\Region;
use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

/**
 * Shared date-window contract for the availability JSON reads.
 *
 * Both the month grid and the mobile exceptions list are bounded windows, not
 * unbounded table scans. The controller supplies the current-month defaults and
 * enforces `calendar_max_days`.
 */
#[MapInputName(SnakeCaseMapper::class)]
final class AvailabilityWindowFilterData extends Data
{
    public function __construct(
        public ?string $from = null,
        public ?string $to = null,
        public ?string $region = null,
    ) {}

    /**
     * @return array<string, array<int, mixed>>
     */
    public static function rules(): array
    {
        return [
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
            'region' => ['nullable', 'string', 'in:'.implode(',', array_column(Region::cases(), 'value'))],
        ];
    }
}
