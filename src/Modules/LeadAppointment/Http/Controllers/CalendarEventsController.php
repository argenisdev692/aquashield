<?php

declare(strict_types=1);

namespace Modules\LeadAppointment\Http\Controllers;

use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Modules\LeadAppointment\Application\Dtos\CalendarEventsData;
use Modules\LeadAppointment\Application\Dtos\CalendarWindowFilterData;
use Modules\LeadAppointment\Application\Ports\AppointmentReader;
use Modules\LeadAppointment\Domain\Enums\Region;

/**
 * The dashboard calendar's bounded read.
 *
 * The browser owns the visible month (FullCalendar `datesSet`); this endpoint
 * answers exactly that window with the slim event projection. The query is
 * padded by one day on each side so a Florida visit whose Eastern-local date
 * falls inside the visible grid is not lost to the company timezone boundary.
 */
final readonly class CalendarEventsController
{
    public function __construct(private AppointmentReader $appointments) {}

    public function __invoke(Request $request): CalendarEventsData
    {
        $filters = CalendarWindowFilterData::validateAndCreate($request->query());
        $timezone = Region::from(
            (string) config('lead-appointment.default_region', Region::Houston->value),
        )->timezone();

        $maxDays = (int) config('lead-appointment.calendar_max_days', 92);

        if (CarbonImmutable::parse($filters->from)->diffInDays(CarbonImmutable::parse($filters->to)) + 1 > $maxDays) {
            throw ValidationException::withMessages([
                'to' => "The calendar window is limited to {$maxDays} days.",
            ]);
        }

        $from = CarbonImmutable::parse($filters->from, $timezone)->subDay()->startOfDay();
        $to = CarbonImmutable::parse($filters->to, $timezone)->addDay()->endOfDay();

        return new CalendarEventsData(
            $this->appointments->calendarEventsBetween($from, $to, $filters->region),
        );
    }
}
