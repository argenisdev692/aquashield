<?php

declare(strict_types=1);

namespace Modules\LeadAppointment\Domain\Services;

use Carbon\CarbonImmutable;
use Modules\LeadAppointment\Domain\Ports\AvailabilityScheduleReader;
use Modules\LeadAppointment\Domain\Ports\HolidayProvider;

/**
 * Materialises public holidays as company-wide `availability_exceptions` rows
 * with `source=holiday`. Manual overrides are never touched: a manager who
 * opened a holiday for a crew made a deliberate decision.
 */
final readonly class HolidayMaterializer
{
    public function __construct(
        private HolidayProvider $holidays,
        private AvailabilityScheduleReader $schedule,
    ) {}

    /**
     * @return array{country: string, created: int, skipped: int}
     */
    public function materialize(int $year): array
    {
        $created = 0;
        $skipped = 0;

        foreach ($this->holidays->forYear($year) as $date => $name) {
            if ($this->schedule->findActiveException($date) !== null) {
                $skipped++;

                continue;
            }

            $this->schedule->createHolidayException($date, $name);
            $created++;
        }

        return [
            'country' => $this->holidays->countryCode(),
            'created' => $created,
            'skipped' => $skipped,
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
        $purged = $this->schedule->purgeHolidayExceptionsFrom(CarbonImmutable::today()->toDateString());
        $created = 0;

        foreach ([CarbonImmutable::now()->year, CarbonImmutable::now()->year + 1] as $year) {
            $created += $this->materialize($year)['created'];
        }

        return ['purged' => $purged, 'created' => $created];
    }
}
