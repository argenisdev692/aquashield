<?php

declare(strict_types=1);

namespace Modules\LeadAppointment\Http\Controllers;

use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Modules\LeadAppointment\Application\Dtos\CalendarFeedData;
use Modules\LeadAppointment\Application\Queries\CalendarFeedQuery;
use Modules\LeadAppointment\Domain\Enums\Region;

final readonly class CalendarController
{
    public function __construct(private CalendarFeedQuery $feed) {}

    public function __invoke(Request $request): CalendarFeedData|JsonResponse
    {
        $validated = $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
            'region' => ['nullable', Rule::enum(Region::class)],
        ]);

        $from = $validated['from'] ?? CarbonImmutable::now()->startOfMonth()->toDateString();
        $to = $validated['to'] ?? CarbonImmutable::now()->endOfMonth()->toDateString();
        $maxDays = (int) config('lead-appointment.calendar_max_days', 92);

        if (CarbonImmutable::parse($from)->diffInDays(CarbonImmutable::parse($to)) > $maxDays) {
            return response()->json([
                'message' => "The calendar window is limited to {$maxDays} days.",
            ], 422);
        }

        return $this->feed->execute(
            from: $from,
            to: $to,
            region: $validated['region'] ?? null,
        );
    }
}
