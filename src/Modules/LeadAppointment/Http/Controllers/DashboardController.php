<?php

declare(strict_types=1);

namespace Modules\LeadAppointment\Http\Controllers;

use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Inertia\Response as InertiaResponse;
use Modules\LeadAppointment\Application\Ports\AppointmentRepository;
use Modules\LeadAppointment\Application\Ports\LeadRepository;
use Modules\LeadAppointment\Application\Queries\DashboardStatsQuery;
use Modules\LeadAppointment\Domain\Enums\AppointmentKind;
use Modules\LeadAppointment\Domain\Enums\LeadStatus;
use Modules\LeadAppointment\Domain\Enums\Region;

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
        private LeadRepository $leads,
        private AppointmentRepository $appointments,
        private DashboardStatsQuery $stats,
    ) {}

    public function __invoke(Request $request): InertiaResponse
    {
        $canViewLeads = $request->user()?->can('VIEW_ANY_LEAD') ?? false;
        $canViewAppointments = $request->user()?->can('VIEW_ANY_APPOINTMENT') ?? false;
        $canCreateLead = $request->user()?->can('CREATE_LEAD') ?? false;
        $canUpdateLead = $request->user()?->can('UPDATE_LEAD') ?? false;
        $canScheduleAppointment = $request->user()?->can('CREATE_APPOINTMENT') ?? false;
        $columns = $canViewLeads ? $this->leads->board() : [];

        $timezone = Region::from((string) config('lead-appointment.default_region', Region::Houston->value))
            ->timezone();
        $now = CarbonImmutable::now($timezone);
        $from = $now->startOfMonth();
        $to = $now->endOfMonth();

        $appointments = $canViewAppointments
            ? $this->appointments->calendarBetween($from, $to, null)
            : [];

        return inertia('Dashboard', [
            'columns' => $columns,
            'transitions' => LeadStatus::transitionMap(),
            'appointments' => $appointments,
            'stats' => $this->stats->execute($now, $canViewLeads, $canViewAppointments),
            'canCreate' => $canCreateLead,
            'canUpdate' => $canUpdateLead,
            'canScheduleAppointment' => $canScheduleAppointment,
            'leadOptions' => [
                'regions' => array_map(
                    static fn (Region $region): array => ['value' => $region->value, 'label' => $region->label()],
                    Region::cases(),
                ),
                'owners' => $canCreateLead ? $this->leads->assignableOwners() : [],
            ],
            'googleMapsApiKey' => (string) config('services.google.maps_key', ''),
            'options' => [
                'kinds' => array_map(
                    static fn (AppointmentKind $kind): array => ['value' => $kind->value, 'label' => $kind->label()],
                    AppointmentKind::cases(),
                ),
                'regions' => array_map(
                    static fn (Region $region): array => ['value' => $region->value, 'label' => $region->label()],
                    Region::cases(),
                ),
            ],
            'defaultDuration' => (int) config('lead-appointment.inspection_duration_minutes', 420),
            'defaultTimezone' => $timezone,
        ]);
    }
}
