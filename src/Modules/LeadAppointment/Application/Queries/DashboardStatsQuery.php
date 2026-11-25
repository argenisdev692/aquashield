<?php

declare(strict_types=1);

namespace Modules\LeadAppointment\Application\Queries;

use Carbon\CarbonImmutable;
use Modules\LeadAppointment\Application\Dtos\DashboardMetricData;
use Modules\LeadAppointment\Application\Dtos\DashboardStatsData;
use Modules\LeadAppointment\Application\Ports\AppointmentReader;
use Modules\LeadAppointment\Application\Ports\LeadReader;
use Modules\LeadAppointment\Domain\Enums\AppointmentKind;

/**
 * Month-over-month counters for the dashboard KPI row.
 *
 * Every card and its chip share one scope: the calendar month in the company's
 * timezone. The pipeline snapshot lives in the board below, so these KPIs
 * answer "how is this month going?" instead of repeating the column totals.
 *
 * Counts are zeroed when the viewer cannot see the underlying records, matching
 * the gated board and calendar props on the same page.
 */
final readonly class DashboardStatsQuery
{
    public function __construct(
        private LeadReader $leads,
        private AppointmentReader $appointments,
    ) {}

    public function execute(
        CarbonImmutable $now,
        bool $includeLeads = true,
        bool $includeAppointments = true,
    ): DashboardStatsData {
        $currentStart = $now->startOfMonth();
        $currentEnd = $now->endOfMonth();
        $previousStart = $currentStart->subMonthNoOverflow()->startOfMonth();
        $previousEnd = $currentStart->subSecond();

        $empty = new DashboardMetricData(0, 0);

        return new DashboardStatsData(
            leads: $includeLeads
                ? new DashboardMetricData(
                    current: $this->leads->countCreatedBetween($currentStart, $currentEnd),
                    previous: $this->leads->countCreatedBetween($previousStart, $previousEnd),
                )
                : $empty,
            inspections: $includeAppointments
                ? new DashboardMetricData(
                    current: $this->appointments->countScheduledBetween(
                        $currentStart,
                        $currentEnd,
                        AppointmentKind::Inspection,
                    ),
                    previous: $this->appointments->countScheduledBetween(
                        $previousStart,
                        $previousEnd,
                        AppointmentKind::Inspection,
                    ),
                )
                : $empty,
            won: $includeLeads
                ? new DashboardMetricData(
                    current: $this->leads->countWonBetween($currentStart, $currentEnd),
                    previous: $this->leads->countWonBetween($previousStart, $previousEnd),
                )
                : $empty,
            appointments: $includeAppointments
                ? new DashboardMetricData(
                    current: $this->appointments->countScheduledBetween($currentStart, $currentEnd),
                    previous: $this->appointments->countScheduledBetween($previousStart, $previousEnd),
                )
                : $empty,
        );
    }
}
