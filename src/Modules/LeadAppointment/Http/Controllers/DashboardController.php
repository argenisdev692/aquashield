<?php

declare(strict_types=1);

namespace Modules\LeadAppointment\Http\Controllers;

use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Inertia\Response as InertiaResponse;
use Modules\LeadAppointment\Application\Ports\LeadReader;
use Modules\LeadAppointment\Application\Queries\DashboardStatsQuery;
use Modules\LeadAppointment\Domain\Enums\AppointmentKind;
use Modules\LeadAppointment\Domain\Enums\LeadStatus;
use Modules\LeadAppointment\Domain\Enums\Region;
use Shared\Application\DTOs\SelectOptionData;
use Shared\Domain\Enums\Permission;

/**
 * The real dashboard: the same server-grouped lead columns as `/leads/board`
 * and the current month of appointments. No mock rows, no client-side copy of
 * the pipeline.
 *
 * The KPI row is month-over-month from the same database — leads captured,
 * inspections scheduled, wins and appointments, each against the previous
 * calendar month — so the chips are a measurement, not a caption.
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
        $columns = $canViewLeads ? $this->leads->board() : [];

        $timezone = Region::from((string) config('lead-appointment.default_region', Region::Houston->value))
            ->timezone();
        $now = CarbonImmutable::now($timezone);

        return inertia('Dashboard', [
            'columns' => $columns,
            'transitions' => LeadStatus::transitionMap(),
            'stats' => $this->stats->execute($now, $canViewLeads, $canViewAppointments),
            'canCreate' => $canCreateLead,
            'canUpdate' => $canUpdateLead,
            'canViewLeads' => $canViewLeads,
            'canScheduleAppointment' => $canScheduleAppointment,
            'canViewCalendar' => $canViewAppointments,
            'canViewAppointment' => $canViewAppointment,
            'canViewLead' => $canViewLead,
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
            ],
            'defaultDuration' => (int) config('lead-appointment.inspection_duration_minutes', 420),
            'defaultTimezone' => $timezone,
        ]);
    }
}
