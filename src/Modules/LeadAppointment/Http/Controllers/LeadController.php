<?php

declare(strict_types=1);

namespace Modules\LeadAppointment\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Response as InertiaResponse;
use Modules\LeadAppointment\Application\Actions\CaptureLeadAction;
use Modules\LeadAppointment\Application\Actions\MergeLeadAction;
use Modules\LeadAppointment\Application\Actions\UpdateLeadAction;
use Modules\LeadAppointment\Application\Actions\UpdateLeadStatusAction;
use Modules\LeadAppointment\Application\Dtos\AssignableOwnerData;
use Modules\LeadAppointment\Application\Dtos\CaptureLeadData;
use Modules\LeadAppointment\Application\Dtos\LogLeadActivityData;
use Modules\LeadAppointment\Application\Dtos\MergeLeadData;
use Modules\LeadAppointment\Application\Dtos\UpdateLeadData;
use Modules\LeadAppointment\Application\Dtos\UpdateLeadStatusData;
use Modules\LeadAppointment\Application\Ports\LeadReader;
use Modules\LeadAppointment\Application\Ports\LeadWriter;
use Modules\LeadAppointment\Domain\Enums\LeadSource;
use Modules\LeadAppointment\Domain\Enums\LeadStatus;
use Modules\LeadAppointment\Domain\Enums\Region;
use Modules\LeadAppointment\Domain\Exceptions\InvalidStatusTransitionException;
use Modules\LeadAppointment\Domain\Exceptions\LeadRestoreConflictException;
use Modules\LeadAppointment\Http\Requests\LogLeadActivityRequest;
use Modules\LeadAppointment\Http\Requests\MergeLeadRequest;
use Modules\LeadAppointment\Http\Requests\StoreLeadRequest;
use Modules\LeadAppointment\Http\Requests\UpdateLeadRequest;
use Modules\LeadAppointment\Http\Requests\UpdateLeadStatusRequest;
use Modules\LeadAppointment\Http\Support\LeadFilterFactory;
use Shared\Application\DTOs\BulkUuidsData;
use Shared\Application\DTOs\SelectOptionData;
use Shared\Domain\Enums\Permission;

final readonly class LeadController
{
    public function __construct(
        private LeadReader $leads,
        private LeadWriter $leadWriter,
        private CaptureLeadAction $captureLead,
        private UpdateLeadAction $updateLead,
        private UpdateLeadStatusAction $updateLeadStatus,
        private MergeLeadAction $mergeLead,
    ) {}

    /**
     * One read, two transports: the DataTable kit receives `rows` + `total` as
     * Inertia props, and /api/v1 receives the same page as JSON.
     */
    public function index(Request $request): JsonResponse|InertiaResponse
    {
        $filters = LeadFilterFactory::fromRequest($request);
        $page = $this->leads->paginate($filters);

        if ($request->wantsJson()) {
            return response()->json([
                'rows' => $page->items(),
                'total' => $page->total(),
                'page' => $page->currentPage(),
                'per_page' => $page->perPage(),
            ]);
        }

        return inertia('Leads/Index', [
            'rows' => $page->items(),
            'total' => $page->total(),
            'currentPage' => $page->currentPage(),
            'perPage' => $page->perPage(),
            'options' => $this->options(),
            'canCreate' => $this->can($request, Permission::CreateLead->value),
            'canView' => $this->can($request, Permission::ViewLead->value),
            'canUpdate' => $this->can($request, Permission::UpdateLead->value)
                && $this->can($request, Permission::ViewLead->value),
            'canDelete' => $this->can($request, Permission::DeleteLead->value),
            'canRestore' => $this->can($request, Permission::RestoreLead->value)
                && $this->can($request, Permission::ViewLead->value),
            'canExport' => $this->can($request, Permission::ExportLead->value),
            'canBulkDelete' => $this->can($request, Permission::BulkDeleteLead->value),
            'canBulkRestore' => $this->can($request, Permission::BulkRestoreLead->value),
        ]);
    }

    /**
     * The pipeline board. Server-grouped by stage so the browser never filters
     * a paginated response; a drag is a status write and a page reload.
     */
    public function board(Request $request): JsonResponse|InertiaResponse
    {
        $columns = $this->leads->board();

        if ($request->wantsJson()) {
            return response()->json(['columns' => $columns]);
        }

        return inertia('Leads/Board', [
            'columns' => $columns,
            'transitions' => $this->transitions(),
            'canUpdate' => $this->can($request, Permission::UpdateLead->value)
                && $this->can($request, Permission::ViewLead->value),
            'canCreate' => $this->can($request, Permission::CreateLead->value),
            'canView' => $this->can($request, Permission::ViewLead->value),
        ]);
    }

    public function create(): InertiaResponse
    {
        return inertia('Leads/Create', [
            'options' => $this->options(),
            'googleMapsApiKey' => (string) config('services.google.maps_key', ''),
        ]);
    }

    public function store(StoreLeadRequest $request): JsonResponse|RedirectResponse
    {
        $lead = $this->captureLead->execute(
            data: CaptureLeadData::from($request->validated()),
            source: LeadSource::Manual,
            sourceDetail: 'Admin',
            actorId: $request->user()?->id,
        );

        if ($request->wantsJson()) {
            return response()->json($lead, 201);
        }

        return redirect()
            ->route('leads.show', $lead->uuid)
            ->with('status', 'Lead created.');
    }

    public function show(Request $request, string $uuid): JsonResponse|InertiaResponse
    {
        $lead = $this->leads->find($uuid, true);

        if ($lead === null) {
            abort(404);
        }

        if ($request->wantsJson()) {
            return response()->json($lead);
        }

        return inertia('Leads/Show', [
            'lead' => $lead,
            'activities' => $this->leads->activities($uuid),
            'transitions' => $this->transitions(),
            'canUpdate' => $this->can($request, Permission::UpdateLead->value)
                && $this->can($request, Permission::ViewLead->value),
            'canDelete' => $this->can($request, Permission::DeleteLead->value),
            'canRestore' => $this->can($request, Permission::RestoreLead->value)
                && $this->can($request, Permission::ViewLead->value),
        ]);
    }

    public function edit(string $uuid): InertiaResponse
    {
        $lead = $this->leads->find($uuid);

        if ($lead === null) {
            abort(404);
        }

        return inertia('Leads/Edit', [
            'lead' => $lead,
            'options' => $this->options(),
            'googleMapsApiKey' => (string) config('services.google.maps_key', ''),
        ]);
    }

    public function update(UpdateLeadRequest $request, string $uuid): JsonResponse|RedirectResponse
    {
        $lead = $this->updateLead->execute($uuid, UpdateLeadData::from($request->validated()));

        if ($request->wantsJson()) {
            return response()->json($lead);
        }

        return redirect()
            ->route('leads.show', $lead->uuid)
            ->with('status', 'Lead updated.');
    }

    public function updateStatus(UpdateLeadStatusRequest $request, string $uuid): JsonResponse|RedirectResponse
    {
        try {
            $lead = $this->updateLeadStatus->execute(
                $uuid,
                UpdateLeadStatusData::from($request->validated()),
                $request->user()?->id,
            );
        } catch (InvalidStatusTransitionException $exception) {
            if ($request->wantsJson()) {
                throw $exception;
            }

            return back()->withErrors(['status' => $exception->getMessage()]);
        }

        if ($request->wantsJson()) {
            return response()->json($lead);
        }

        return back()->with('status', 'Pipeline stage updated.');
    }

    public function logActivity(LogLeadActivityRequest $request, string $uuid): JsonResponse|RedirectResponse
    {
        $activity = $this->leadWriter->logActivity(
            $uuid,
            LogLeadActivityData::from($request->validated()),
            $request->user()?->id,
        );

        if ($request->wantsJson()) {
            return response()->json($activity, 201);
        }

        return back()->with('status', 'Activity logged.');
    }

    public function merge(MergeLeadRequest $request, string $uuid): JsonResponse
    {
        return response()->json(
            $this->mergeLead->execute($uuid, MergeLeadData::from($request->validated())->duplicateUuid),
        );
    }

    public function destroy(Request $request, string $uuid): JsonResponse|RedirectResponse
    {
        $this->leadWriter->delete($uuid);

        if ($request->wantsJson()) {
            return response()->json(null, 204);
        }

        return redirect()
            ->route('leads.index')
            ->with('status', 'Lead deleted.');
    }

    public function restore(Request $request, string $uuid): JsonResponse|RedirectResponse
    {
        try {
            $lead = $this->leadWriter->restore($uuid);
        } catch (LeadRestoreConflictException $exception) {
            if ($request->wantsJson()) {
                throw $exception;
            }

            return back()->withErrors(['lead' => $exception->getMessage()]);
        }

        if ($request->wantsJson()) {
            return response()->json($lead);
        }

        return back()->with('status', 'Lead restored.');
    }

    public function bulkDelete(Request $request): JsonResponse
    {
        $data = BulkUuidsData::validateAndCreate($request->all());
        $this->leadWriter->bulkDelete($data->uuids);

        return response()->json(null, 204);
    }

    public function bulkRestore(Request $request): JsonResponse
    {
        $data = BulkUuidsData::validateAndCreate($request->all());
        $this->leadWriter->bulkRestore($data->uuids);

        return response()->json(null, 204);
    }

    /**
     * The only catalog the lead screens need. Owners are real users; the rest
     * are the enum's own labels, so a new stage or metro appears automatically.
     *
     * @return array{
     *     statuses: list<SelectOptionData>,
     *     sources: list<SelectOptionData>,
     *     regions: list<SelectOptionData>,
     *     owners: list<AssignableOwnerData>
     * }
     */
    private function options(): array
    {
        return [
            'statuses' => array_map(
                static fn (LeadStatus $status): SelectOptionData => new SelectOptionData($status->value, $status->label()),
                LeadStatus::cases(),
            ),
            'sources' => array_map(
                static fn (LeadSource $source): SelectOptionData => new SelectOptionData($source->value, $source->label()),
                LeadSource::cases(),
            ),
            'regions' => array_map(
                static fn (Region $region): SelectOptionData => new SelectOptionData($region->value, $region->label()),
                Region::cases(),
            ),
            'owners' => $this->leads->assignableOwners(),
        ];
    }

    /**
     * The allowed next stages per stage. The board uses it to disable illegal
     * drops and the detail screen to render only legal options.
     *
     * @return array<string, list<string>>
     */
    private function transitions(): array
    {
        return LeadStatus::manualTransitionMap();
    }

    private function can(Request $request, string $permission): bool
    {
        return $request->user()?->can($permission) ?? false;
    }
}
