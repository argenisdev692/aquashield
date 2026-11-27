<?php

declare(strict_types=1);

namespace Modules\LeadAppointment\Application\Queries;

use Carbon\CarbonImmutable;
use Modules\LeadAppointment\Application\Dtos\DashboardMetricData;
use Modules\LeadAppointment\Application\Dtos\DashboardStatsData;
use Modules\LeadAppointment\Application\Ports\AppointmentReader;
use Modules\LeadAppointment\Application\Ports\LeadReader;
use Modules\LeadAppointment\Domain\Enums\AppointmentKind;
use Modules\LeadAppointment\Domain\Enums\DashboardRange;
use Modules\LeadAppointment\Domain\ValueObjects\DateWindow;

/**
 * Period-over-period counters and daily series for the dashboard KPI row.
 *
 * Every card, its chip and its sparkline share one scope: the selected period
 * in the company's timezone, plus the dashboard's region/source filters. The
 * board below reads the same scope, so a KPI can never count rows the board
 * hides.
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
        DashboardRange $range = DashboardRange::Last30Days,
        ?string $region = null,
        ?string $source = null,
    ): DashboardStatsData {
        $timezone = $now->getTimezone()->getName();
        $current = $range->window($now);
        $previous = $range->comparisonWindow($now);

        $empty = new DashboardMetricData(0, 0);

        $leads = $empty;
        $won = $empty;

        if ($includeLeads) {
            $leads = new DashboardMetricData(
                current: $this->leads->countCreatedBetween(
                    $current->start,
                    $current->end,
                    $region,
                    $source,
                ),
                previous: $this->leads->countCreatedBetween(
                    $previous->start,
                    $previous->end,
                    $region,
                    $source,
                ),
                series: $this->fillSeries(
                    $this->leads->dailyCreatedBetween(
                        $current->start,
                        $current->end,
                        $timezone,
                        $region,
                        $source,
                    ),
                    $current,
                    $timezone,
                ),
            );

            $won = new DashboardMetricData(
                current: $this->leads->countWonBetween(
                    $current->start,
                    $current->end,
                    $region,
                    $source,
                ),
                previous: $this->leads->countWonBetween(
                    $previous->start,
                    $previous->end,
                    $region,
                    $source,
                ),
                series: $this->fillSeries(
                    $this->leads->dailyWonBetween(
                        $current->start,
                        $current->end,
                        $timezone,
                        $region,
                        $source,
                    ),
                    $current,
                    $timezone,
                ),
            );
        }

        $inspections = $empty;
        $appointments = $empty;

        if ($includeAppointments) {
            $inspections = new DashboardMetricData(
                current: $this->appointments->countScheduledBetween(
                    $current->start,
                    $current->end,
                    AppointmentKind::Inspection,
                    $region,
                ),
                previous: $this->appointments->countScheduledBetween(
                    $previous->start,
                    $previous->end,
                    AppointmentKind::Inspection,
                    $region,
                ),
                series: $this->fillSeries(
                    $this->appointments->dailyScheduledBetween(
                        $current->start,
                        $current->end,
                        $timezone,
                        AppointmentKind::Inspection,
                        $region,
                    ),
                    $current,
                    $timezone,
                ),
            );

            $appointments = new DashboardMetricData(
                current: $this->appointments->countScheduledBetween(
                    $current->start,
                    $current->end,
                    null,
                    $region,
                ),
                previous: $this->appointments->countScheduledBetween(
                    $previous->start,
                    $previous->end,
                    null,
                    $region,
                ),
                series: $this->fillSeries(
                    $this->appointments->dailyScheduledBetween(
                        $current->start,
                        $current->end,
                        $timezone,
                        null,
                        $region,
                    ),
                    $current,
                    $timezone,
                ),
            );
        }

        return new DashboardStatsData(
            leads: $leads,
            inspections: $inspections,
            won: $won,
            appointments: $appointments,
        );
    }

    /**
     * One entry per day in the window, in company timezone order. A day with
     * no rows is a real zero — dropping it would compress the x axis and make
     * a quiet week look as dense as a busy one.
     *
     * @param array<string, int> $counts
     * @return list<int>
     */
    private function fillSeries(array $counts, DateWindow $window, string $timezone): array
    {
        $series = [];
        $cursor = $window->start->setTimezone($timezone)->startOfDay();
        $last = $window->end->setTimezone($timezone)->startOfDay();

        while ($cursor->lessThanOrEqualTo($last)) {
            $series[] = $counts[$cursor->toDateString()] ?? 0;
            $cursor = $cursor->addDay();
        }

        return $series;
    }
}
