<?php

declare(strict_types=1);

namespace Modules\LeadAppointment\Application\Dtos;

use Modules\LeadAppointment\Domain\Enums\Region;
use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

/**
 * Required window for the dashboard calendar feed.
 *
 * `from`/`to` are calendar dates, not instants. The controller reads them in
 * the company timezone and pads the query by one day on each side so an
 * Eastern-region visit that falls just outside a Central-month boundary is
 * still available to the view that displays it.
 */
#[MapInputName(SnakeCaseMapper::class)]
final class CalendarWindowFilterData extends Data
{
    public function __construct(
        public string $from,
        public string $to,
        public ?string $region = null,
    ) {}

    /**
     * @return array<string, array<int, string>>
     */
    public static function rules(): array
    {
        return [
            'from' => ['required', 'date_format:Y-m-d'],
            'to' => ['required', 'date_format:Y-m-d', 'after_or_equal:from'],
            'region' => ['nullable', 'string', 'in:'.implode(',', array_column(Region::cases(), 'value'))],
        ];
    }
}
