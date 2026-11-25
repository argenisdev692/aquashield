<?php

declare(strict_types=1);

namespace Modules\LeadAppointment\Application\Queries;

use Carbon\CarbonImmutable;
use Modules\LeadAppointment\Application\Dtos\AppointmentData;
use Modules\LeadAppointment\Application\Dtos\CalendarDayData;
use Modules\LeadAppointment\Application\Dtos\CalendarFeedData;
use Modules\LeadAppointment\Application\Ports\AppointmentReader;
use Modules\LeadAppointment\Domain\Enums\Region;
use Modules\LeadAppointment\Domain\Services\AvailabilityResolver;
use Modules\LeadAppointment\Domain\ValueObjects\ResolvedDay;
use Modules\LeadAppointment\Domain\ValueObjects\TimeWindow;

/**
 * One bounded read for the month/week calendar. Availability is batched (four
 * queries for the window), appointments are one query, and the grouping happens
 * in memory. The feed is local-time; the JSON also carries the timezone.
 */
final readonly class CalendarFeedQuery
{
    public function __construct(
        private AppointmentReader $appointments,
        private AvailabilityResolver $availability,
    ) {}

    public function execute(
        string $from,
        string $to,
        ?string $region = null,
    ): CalendarFeedData {
        $timezone = Region::from($region ?? (string) config('lead-appointment.default_region', Region::Houston->value))
            ->timezone();

        $start = CarbonImmutable::parse($from, $timezone)->startOfDay();
        $end = CarbonImmutable::parse($to, $timezone)->endOfDay();

        $days = $this->availability->resolveRange($start, $end);
        $appointments = $this->appointments->calendarBetween($start, $end, $region);

        /** @var array<string, list<AppointmentData>> $byDay */
        $byDay = [];

        foreach ($appointments as $appointment) {
            $key = CarbonImmutable::parse($appointment->startsAt)->setTimezone($timezone)->toDateString();
            $byDay[$key][] = $appointment;
        }

        $calendarDays = [];

        foreach ($days as $date => $resolved) {
            $calendarDays[] = $this->toDay($date, $resolved, $byDay[$date] ?? []);
        }

        return new CalendarFeedData(
            from: $start->toDateString(),
            to: $end->toDateString(),
            timezone: $timezone,
            days: $calendarDays,
        );
    }

    /**
     * @param list<AppointmentData> $appointments
     */
    private function toDay(string $date, ResolvedDay $resolved, array $appointments): CalendarDayData
    {
        return new CalendarDayData(
            date: $date,
            isOpen: $resolved->isOpen,
            source: $resolved->source,
            reason: $resolved->reason,
            windows: array_map(
                static fn (TimeWindow $window): array => ['start' => $window->start, 'end' => $window->end],
                $resolved->windows,
            ),
            appointments: $appointments,
        );
    }
}
