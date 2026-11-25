<?php

declare(strict_types=1);

namespace Modules\LeadAppointment\Domain\Services;

use Carbon\CarbonImmutable;
use Modules\LeadAppointment\Domain\Ports\HolidayProvider;
use Modules\LeadAppointment\Domain\Ports\HolidayScheduleWriter;

/**
 * Materialises public holidays as company-wide `availability_exceptions` rows
 * with `source=holiday`. Manual overrides are never touched: a manager who
 * opened a holiday for a crew made a deliberate decision.
 *
 * The purge and the inserts are one call into the writer port, so a failed sync
 * cannot leave the calendar without holidays.
 */
final readonly class HolidayMaterializer
{
    public function __construct(
        private HolidayProvider $holidays,
        private HolidayScheduleWriter $writer,
    ) {}

    /**
     * @return array{country: string, created: int, skipped: int}
     */
    public function materialize(int $year): array
    {
        $result = $this->writer->syncHolidays($this->holidays->forYear($year));

        return [
            'country' => $this->holidays->countryCode(),
            'created' => $result['created'],
            'skipped' => $result['skipped'],
        ];
    }

    /**
     * Rebuild the current and next year's holidays from today forward. Purges
     * only holiday-sourced rows dated today or later; manual rows and past
     * history survive.
     *
     * @return array{purged: int, created: int}
     */
    public function resync(): array
    {
        $holidays = [];

        foreach ([CarbonImmutable::now()->year, CarbonImmutable::now()->year + 1] as $year) {
            $holidays += $this->holidays->forYear($year);
        }

        $result = $this->writer->syncHolidays(
            $holidays,
            CarbonImmutable::today()->toDateString(),
        );

        return ['purged' => $result['purged'], 'created' => $result['created']];
    }
}
