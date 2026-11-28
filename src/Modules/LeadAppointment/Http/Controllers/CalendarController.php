<?php

declare(strict_types=1);

namespace Modules\LeadAppointment\Http\Controllers;

use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Modules\LeadAppointment\Application\Dtos\AvailabilityWindowFilterData;
use Modules\LeadAppointment\Application\Dtos\CalendarFeedData;
use Modules\LeadAppointment\Application\Queries\CalendarFeedQuery;

final readonly class CalendarController
{
    public function __construct(private CalendarFeedQuery $feed) {}

    public function __invoke(Request $request): CalendarFeedData
    {
        $filters = AvailabilityWindowFilterData::validateAndCreate($request->query());

        $from = $filters->from ?? CarbonImmutable::now()->startOfMonth()->toDateString();
        $to = $filters->to ?? CarbonImmutable::now()->endOfMonth()->toDateString();
        $maxDays = (int) config('lead-appointment.calendar_max_days', 92);

        if (CarbonImmutable::parse($from)->diffInDays(CarbonImmutable::parse($to)) + 1 > $maxDays) {
            throw ValidationException::withMessages([
                'to' => "The calendar window is limited to {$maxDays} days.",
            ]);
        }

        return $this->feed->execute(
            from: $from,
            to: $to,
            region: $filters->region,
        );
    }
}
