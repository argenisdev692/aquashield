<?php

declare(strict_types=1);

namespace Modules\LeadAppointment\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Response as InertiaResponse;
use Modules\LeadAppointment\Application\Actions\CancelAppointmentAction;
use Modules\LeadAppointment\Application\Actions\CompleteAppointmentAction;
use Modules\LeadAppointment\Application\Actions\ConfirmAppointmentAction;
use Modules\LeadAppointment\Application\Actions\RescheduleAppointmentAction;
use Modules\LeadAppointment\Application\Actions\ScheduleAppointmentAction;
use Modules\LeadAppointment\Application\Dtos\AppointmentData;
use Modules\LeadAppointment\Application\Dtos\CancelAppointmentData;
use Modules\LeadAppointment\Application\Dtos\RescheduleAppointmentData;
use Modules\LeadAppointment\Application\Dtos\ScheduleAppointmentData;
use Modules\LeadAppointment\Application\Ports\AppointmentReader;
use Modules\LeadAppointment\Application\Ports\AppointmentWriter;
use Modules\LeadAppointment\Domain\Enums\AppointmentKind;
use Modules\LeadAppointment\Domain\Enums\AppointmentStatus;
use Modules\LeadAppointment\Domain\Enums\Region;
use Modules\LeadAppointment\Domain\Exceptions\AvailabilityConflictException;
use Modules\LeadAppointment\Domain\Exceptions\CapacityExceededException;
use Modules\LeadAppointment\Domain\Exceptions\InvalidStatusTransitionException;
use Modules\LeadAppointment\Domain\Exceptions\PastDateSchedulingException;
use Modules\LeadAppointment\Domain\Exceptions\SlotUnavailableException;
use Modules\LeadAppointment\Http\Requests\CancelAppointmentRequest;
use Modules\LeadAppointment\Http\Requests\RescheduleAppointmentRequest;
use Modules\LeadAppointment\Http\Requests\StoreAppointmentRequest;
use Modules\LeadAppointment\Http\Support\AppointmentFilterFactory;
use Shared\Application\DTOs\BulkUuidsData;
use Shared\Application\DTOs\SelectOptionData;
use Shared\Domain\Enums\Permission;

/**
 * One read, two transports: the DataTable kit receives the page as Inertia
 * props on a web visit, while /api/v1 receives the same page as JSON. Writes
 * redirect back to the appointment on the web surface and keep returning the
 * appointment payload to the API, so the React Native client never changes.
 */
final readonly class AppointmentController
{
    public function __construct(
        private AppointmentReader $appointments,
        private AppointmentWriter $appointmentWriter,
        private ScheduleAppointmentAction $scheduleAppointment,
        private RescheduleAppointmentAction $rescheduleAppointment,
        private CancelAppointmentAction $cancelAppointment,
        private CompleteAppointmentAction $completeAppointment,
        private ConfirmAppointmentAction $confirmAppointment,
    ) {}

    public function index(Request $request): JsonResponse|InertiaResponse
    {
        $filters = AppointmentFilterFactory::fromRequest($request);
        $page = $this->appointments->paginate($filters);

        if ($request->wantsJson()) {
            return response()->json([
                'rows' => $page->items(),
                'total' => $page->total(),
                'page' => $page->currentPage(),
                'per_page' => $page->perPage(),
            ]);
        }

        return inertia('Appointments/Index', [
            'rows' => $page->items(),
            'total' => $page->total(),
            'currentPage' => $page->currentPage(),
            'perPage' => $page->perPage(),
            'options' => $this->options(),
            'canCreate' => $this->can($request, Permission::CreateAppointment->value)
                && $this->can($request, Permission::ViewAnyLead->value),
            'canView' => $this->can($request, Permission::ViewAppointment->value),
            'canUpdate' => $this->can($request, Permission::UpdateAppointment->value),
            'canDelete' => $this->can($request, Permission::DeleteAppointment->value),
            'canRestore' => $this->can($request, Permission::RestoreAppointment->value),
            'canExport' => $this->can($request, Permission::ExportAppointment->value),
            'canBulkDelete' => $this->can($request, Permission::BulkDeleteAppointment->value),
            'canBulkRestore' => $this->can($request, Permission::BulkRestoreAppointment->value),
        ]);
    }

    public function create(): InertiaResponse
    {
        return inertia('Appointments/Create', [
            'options' => $this->options(),
            'defaults' => $this->defaults(),
        ]);
    }

    public function store(StoreAppointmentRequest $request): JsonResponse|RedirectResponse
    {
        try {
            $appointment = $this->scheduleAppointment->execute(
                ScheduleAppointmentData::from($request->validated()),
                $request->user()?->id,
            );
        } catch (SlotUnavailableException|PastDateSchedulingException|CapacityExceededException|InvalidStatusTransitionException|AvailabilityConflictException $exception) {
            if ($request->wantsJson()) {
                throw $exception;
            }

            return back()->withErrors(['starts_at' => $exception->getMessage()]);
        }

        if ($request->wantsJson()) {
            return response()->json($appointment, 201);
        }

        return redirect()
            ->route('appointments.show', $appointment->uuid)
            ->with('status', 'Appointment scheduled.');
    }

    public function show(Request $request, string $uuid): JsonResponse|InertiaResponse
    {
        $appointment = $this->appointments->find($uuid, true);

        if ($appointment === null) {
            abort(404);
        }

        if ($request->wantsJson()) {
            return response()->json($appointment);
        }

        return inertia('Appointments/Show', [
            'appointment' => $appointment,
            'canUpdate' => $this->can($request, Permission::UpdateAppointment->value),
            'canDelete' => $this->can($request, Permission::DeleteAppointment->value),
            'canRestore' => $this->can($request, Permission::RestoreAppointment->value),
            'canViewLead' => $this->can($request, Permission::ViewLead->value),
        ]);
    }

    public function edit(string $uuid): InertiaResponse
    {
        $appointment = $this->appointments->find($uuid);

        if ($appointment === null) {
            abort(404);
        }

        return inertia('Appointments/Edit', [
            'appointment' => $appointment,
        ]);
    }

    public function confirm(Request $request, string $uuid): JsonResponse|RedirectResponse
    {
        try {
            $appointment = $this->confirmAppointment->execute($uuid);
        } catch (InvalidStatusTransitionException $exception) {
            return $this->transitionFailure($request, $exception);
        }

        return $this->transitionResponse($request, $appointment, 'Appointment confirmed.');
    }

    public function reschedule(RescheduleAppointmentRequest $request, string $uuid): JsonResponse|RedirectResponse
    {
        try {
            $appointment = $this->rescheduleAppointment->execute(
                $uuid,
                RescheduleAppointmentData::from($request->validated()),
            );
        } catch (SlotUnavailableException|PastDateSchedulingException|CapacityExceededException|InvalidStatusTransitionException|AvailabilityConflictException $exception) {
            if ($request->wantsJson()) {
                throw $exception;
            }

            return back()->withErrors(['starts_at' => $exception->getMessage()]);
        }

        if ($request->wantsJson()) {
            return response()->json($appointment);
        }

        return redirect()
            ->route('appointments.show', $appointment->uuid)
            ->with('status', 'Appointment rescheduled.');
    }

    public function cancel(CancelAppointmentRequest $request, string $uuid): JsonResponse|RedirectResponse
    {
        try {
            $appointment = $this->cancelAppointment->execute(
                $uuid,
                CancelAppointmentData::from($request->validated())->reason,
            );
        } catch (InvalidStatusTransitionException $exception) {
            return $this->transitionFailure($request, $exception);
        }

        return $this->transitionResponse($request, $appointment, 'Appointment cancelled.');
    }

    public function complete(Request $request, string $uuid): JsonResponse|RedirectResponse
    {
        try {
            $appointment = $this->completeAppointment->execute($uuid);
        } catch (InvalidStatusTransitionException $exception) {
            return $this->transitionFailure($request, $exception);
        }

        return $this->transitionResponse($request, $appointment, 'Appointment completed.');
    }

    public function destroy(Request $request, string $uuid): JsonResponse|RedirectResponse
    {
        $this->appointmentWriter->delete($uuid);

        if ($request->wantsJson()) {
            return response()->json(null, 204);
        }

        return redirect()
            ->route('appointments.index')
            ->with('status', 'Appointment deleted.');
    }

    public function restore(Request $request, string $uuid): JsonResponse|RedirectResponse
    {
        $appointment = $this->appointmentWriter->restore($uuid);

        if ($request->wantsJson()) {
            return response()->json($appointment);
        }

        return back()->with('status', 'Appointment restored.');
    }

    public function bulkDelete(Request $request): JsonResponse
    {
        $data = BulkUuidsData::validateAndCreate($request->all());
        $this->appointmentWriter->bulkDelete($data->uuids);

        return response()->json(null, 204);
    }

    public function bulkRestore(Request $request): JsonResponse
    {
        $data = BulkUuidsData::validateAndCreate($request->all());
        $this->appointmentWriter->bulkRestore($data->uuids);

        return response()->json(null, 204);
    }

    /**
     * The catalog the appointment screens need. Labels come from the enum, so
     * a new kind or status appears without touching the frontend.
     *
     * @return array{
     *     statuses: list<SelectOptionData>,
     *     kinds: list<SelectOptionData>,
     *     regions: list<SelectOptionData>
     * }
     */
    private function options(): array
    {
        return [
            'statuses' => array_map(
                static fn (AppointmentStatus $status): SelectOptionData => new SelectOptionData($status->value, $status->label()),
                AppointmentStatus::cases(),
            ),
            'kinds' => array_map(
                static fn (AppointmentKind $kind): SelectOptionData => new SelectOptionData($kind->value, $kind->label()),
                AppointmentKind::cases(),
            ),
            'regions' => array_map(
                static fn (Region $region): SelectOptionData => new SelectOptionData($region->value, $region->label()),
                Region::cases(),
            ),
        ];
    }

    /**
     * @return array{duration_minutes: int, timezone: string}
     */
    private function defaults(): array
    {
        $region = Region::from((string) config('lead-appointment.default_region', Region::Houston->value));

        return [
            'duration_minutes' => (int) config('lead-appointment.inspection_duration_minutes', 420),
            'timezone' => $region->timezone(),
        ];
    }

    private function transitionFailure(Request $request, InvalidStatusTransitionException $exception): RedirectResponse
    {
        if ($request->wantsJson()) {
            throw $exception;
        }

        return back()->withErrors(['status' => $exception->getMessage()]);
    }

    private function transitionResponse(
        Request $request,
        AppointmentData $appointment,
        string $message,
    ): JsonResponse|RedirectResponse {
        if ($request->wantsJson()) {
            return response()->json($appointment);
        }

        return back()->with('status', $message);
    }

    private function can(Request $request, string $permission): bool
    {
        return $request->user()?->can($permission) ?? false;
    }
}
