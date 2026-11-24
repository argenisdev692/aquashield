<?php

declare(strict_types=1);

namespace Modules\LeadAppointment\Tests\Support;

use Modules\LeadAppointment\Domain\Ports\AvailabilityScheduleReader;
use Modules\LeadAppointment\Domain\ValueObjects\AvailabilityException;
use Modules\LeadAppointment\Domain\ValueObjects\TimeWindow;

/**
 * DB-free availability reader used by the resolver unit test. It keeps the
 * company precedence test honest without booting Eloquent.
 */
final readonly class FakeAvailabilityScheduleReader implements AvailabilityScheduleReader
{
    /**
     * @param array<string, AvailabilityException> $companyExceptions
     * @param array<int, list<TimeWindow>> $companyRules
     */
    public function __construct(
        private array $companyExceptions = [],
        private array $companyRules = [],
    ) {}

    public function findActiveException(string $date): ?AvailabilityException
    {
        return $this->companyExceptions[$date] ?? null;
    }

    public function activeExceptionsBetween(string $from, string $to): array
    {
        return array_filter(
            $this->companyExceptions,
            static fn (string $date): bool => $date >= $from && $date <= $to,
            ARRAY_FILTER_USE_KEY,
        );
    }

    public function slotsForDay(int $dayOfWeek): array
    {
        return $this->companyRules[$dayOfWeek] ?? [];
    }

    public function slotsGroupedByDay(): array
    {
        return $this->companyRules;
    }

    public function purgeHolidayExceptionsFrom(string $date): int
    {
        return 0;
    }

    public function createHolidayException(string $date, string $name): void {}
}
