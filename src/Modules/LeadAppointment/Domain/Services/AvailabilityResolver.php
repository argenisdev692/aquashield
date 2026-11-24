<?php

declare(strict_types=1);

namespace Modules\LeadAppointment\Domain\Services;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Modules\LeadAppointment\Domain\Ports\AvailabilityScheduleReader;
use Modules\LeadAppointment\Domain\ValueObjects\AvailabilityException;
use Modules\LeadAppointment\Domain\ValueObjects\ResolvedDay;
use Modules\LeadAppointment\Domain\ValueObjects\TimeWindow;

/**
 * Effective availability for one date. Precedence, highest first:
 *
 *   1. company-wide exception (holiday, office closure)
 *   2. company weekly rules
 *   3. closed
 *
 * Technician-specific overrides return with the Claim + Scope Sheet phase,
 * where the assigned technician is a `User` with the TECHNICAL_SERVICES role,
 * not a row in a parallel technicians table.
 */
final readonly class AvailabilityResolver
{
    public function __construct(private AvailabilityScheduleReader $schedule) {}

    public function resolve(CarbonInterface $date): ResolvedDay
    {
        $key = $date->format('Y-m-d');
        $timezone = $date->getTimezone()->getName();

        $companyException = $this->schedule->findActiveException($key);

        if ($companyException !== null) {
            return $this->fromException($key, $timezone, $companyException);
        }

        $companyRules = $this->schedule->slotsForDay((int) $date->dayOfWeek);

        if ($companyRules !== []) {
            return ResolvedDay::open($key, $companyRules, 'company_rule', $timezone);
        }

        return ResolvedDay::closed($key, 'default', $timezone, 'No availability rule for this weekday.');
    }

    /**
     * Batched resolution for a calendar window: two queries for the whole
     * range, then a pure in-memory loop. This is the read path behind the month
     * view and the availability page.
     *
     * @return array<string, ResolvedDay> keyed by Y-m-d
     */
    public function resolveRange(CarbonInterface $from, CarbonInterface $to): array
    {
        $cursor = CarbonImmutable::parse($from->format('Y-m-d'), $from->getTimezone());
        $end = CarbonImmutable::parse($to->format('Y-m-d'), $from->getTimezone());
        $timezone = $from->getTimezone()->getName();

        $companyExceptions = $this->schedule->activeExceptionsBetween(
            $cursor->toDateString(),
            $end->toDateString(),
        );
        $companyRules = $this->schedule->slotsGroupedByDay();

        $days = [];

        while ($cursor->lessThanOrEqualTo($end)) {
            $key = $cursor->format('Y-m-d');
            $dayOfWeek = (int) $cursor->dayOfWeek;

            $days[$key] = $this->buildDay(
                $key,
                $timezone,
                $companyExceptions[$key] ?? null,
                $companyRules[$dayOfWeek] ?? [],
            );

            $cursor = $cursor->addDay();
        }

        return $days;
    }

    /**
     * @param list<TimeWindow> $companyRules
     */
    private function buildDay(
        string $key,
        string $timezone,
        ?AvailabilityException $companyException,
        array $companyRules,
    ): ResolvedDay {
        if ($companyException !== null) {
            return $this->fromException($key, $timezone, $companyException);
        }

        if ($companyRules !== []) {
            return ResolvedDay::open($key, $companyRules, 'company_rule', $timezone);
        }

        return ResolvedDay::closed($key, 'default', $timezone, 'No availability rule for this weekday.');
    }

    private function fromException(
        string $key,
        string $timezone,
        AvailabilityException $exception,
    ): ResolvedDay {
        if (! $exception->isAvailable) {
            return ResolvedDay::closed($key, 'company_exception', $timezone, $exception->reason);
        }

        if ($exception->startTime === null || $exception->endTime === null) {
            return ResolvedDay::closed(
                $key,
                'company_exception',
                $timezone,
                'An open exception must carry both start and end time.',
            );
        }

        return ResolvedDay::open(
            $key,
            [new TimeWindow($exception->startTime, $exception->endTime)],
            'company_exception',
            $timezone,
            $exception->reason,
        );
    }
}
