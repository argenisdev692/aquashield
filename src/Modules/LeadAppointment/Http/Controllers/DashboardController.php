<?php

declare(strict_types=1);

namespace Modules\LeadAppointment\Http\Controllers;

use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Inertia\Response as InertiaResponse;
use Modules\LeadAppointment\Application\Ports\LeadReader;
use Modules\LeadAppointment\Application\Queries\DashboardStatsQuery;
use Modules\LeadAppointment\Domain\Enums\AppointmentKind;
use Modules\LeadAppointment\Domain\Enums\DashboardRange;
use Modules\LeadAppointment\Domain\Enums\LeadSource;
use Modules\LeadAppointment\Domain\Enums\LeadStatus;
use Modules\LeadAppointment\Domain\Enums\Region;
use Shared\Application\DTOs\SelectOptionData;
use Shared\Domain\Enums\Permission;

/**
 * The real dashboard: the same server-grouped lead columns as `/leads/board`,
 * scoped to the selected period and scope filters, plus the calendar's month.
 *
 * The KPI row compares the selected period with the equal-length period before
 * it, from the same database and the same filters — so the chips are a
 * measurement, not a caption. The period and the scope live in the URL
 * (`range`, `filter[region]`, `filter[source]`); an unknown value falls back to
 * the default instead of failing a bookmarked dashboard.
 */
final readonly class DashboardController
{
    public function __construct(
        private LeadReader $leads,
        private DashboardStatsQuery $stats,
    ) {}

    public function __invoke(Request $request): InertiaResponse
    {
        $user = $request->user();
        $canViewLeads = $user !== null && $user->can(Permission::ViewAnyLead->value);
        $canViewAppointments = $user !== null && $user->can(Permission::ViewAnyAppointment->value);
        $canViewAppointment = $user !== null && $user->can(Permission::ViewAppointment->value);
        $canViewLead = $user !== null && $user->can(Permission::ViewLead->value);
        $canCreateLead = $user !== null && $user->can(Permission::CreateLead->value);
        $canUpdateLead = $user !== null && $user->can(Permission::UpdateLead->value);
        $canScheduleAppointment = $user !== null
            && $user->can(Permission::CreateAppointment->value)
            && $user->can(Permission::ViewAnyLead->value);

        $rangeValue = $request->query('range');
        $range = is_string($rangeValue)
            ? DashboardRange::tryFrom($rangeValue) ?? DashboardRange::Last30Days
            : DashboardRange::Last30Days;

        $filter = $request->input('filter');
        $filter = is_array($filter) ? $filter : [];

        // An unknown or malformed scope value is ignored, not rejected: the
        // dashboard is reachable by hand-edited URL and must still render.
        $regionValue = $filter['region'] ?? null;
        $region = is_string($regionValue) && Region::tryFrom($regionValue) !== null ? $regionValue : null;
        $sourceValue = $filter['source'] ?? null;
        $source = is_string($sourceValue) && LeadSource::tryFrom($sourceValue) !== null ? $sourceValue : null;

        $timezone = Region::from((string) config('lead-appointment.default_region', Region::Houston->value))
            ->timezone();
        $now = CarbonImmutable::now($timezone);
        $window = $range->window($now);

        $columns = $canViewLeads
            ? $this->leads->board(25, $window, $region, $source)
            : [];

        return inertia('Dashboard', [
            'columns' => $columns,
            'transitions' => LeadStatus::transitionMap(),
            'stats' => $this->stats->execute(
                $now,
                $canViewLeads,
                $canViewAppointments,
                $range,
                $region,
                $source,
            ),
            'canCreate' => $canCreateLead,
            'canUpdate' => $canUpdateLead,
            'canViewLeads' => $canViewLeads,
            'canScheduleAppointment' => $canScheduleAppointment,
            'canViewCalendar' => $canViewAppointments,
            'canViewAppointment' => $canViewAppointment,
            'canViewLead' => $canViewLead,
            'range' => $range->value,
            'rangeOptions' => array_map(
                static fn (DashboardRange $option): SelectOptionData => new SelectOptionData(
                    $option->value,
                    $option->label(),
                ),
                DashboardRange::cases(),
            ),
            'rangeFrom' => $window->start->toDateString(),
            'rangeTo' => $window->end->toDateString(),
            'leadOptions' => [
                'regions' => array_map(
                    static fn (Region $region): SelectOptionData => new SelectOptionData($region->value, $region->label()),
                    Region::cases(),
                ),
                'owners' => $canCreateLead ? $this->leads->assignableOwners() : [],
            ],
            'googleMapsApiKey' => (string) config('services.google.maps_key', ''),
            'options' => [
                'kinds' => array_map(
                    static fn (AppointmentKind $kind): SelectOptionData => new SelectOptionData($kind->value, $kind->label()),
                    AppointmentKind::cases(),
                ),
                'regions' => array_map(
                    static fn (Region $region): SelectOptionData => new SelectOptionData($region->value, $region->label()),
                    Region::cases(),
                ),
                'statuses' => array_map(
                    static fn (LeadStatus $status): SelectOptionData => new SelectOptionData($status->value, $status->label()),
                    LeadStatus::cases(),
                ),
                'sources' => array_map(
                    static fn (LeadSource $source): SelectOptionData => new SelectOptionData($source->value, $source->label()),
                    LeadSource::cases(),
                ),
            ],
            'defaultDuration' => (int) config('lead-appointment.inspection_duration_minutes', 420),
            'defaultTimezone' => $timezone,
        ]);
    }
}
