<?php

declare(strict_types=1);

namespace Modules\LeadAppointment\Http\Controllers;

use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Response as InertiaResponse;
use Modules\LeadAppointment\Application\Dtos\SaveAvailabilityExceptionData;
use Modules\LeadAppointment\Application\Ports\AvailabilityReader;
use Modules\LeadAppointment\Application\Ports\AvailabilityWriter;
use Modules\LeadAppointment\Domain\Enums\AvailabilitySource;
use Modules\LeadAppointment\Domain\Enums\Region;
use Modules\LeadAppointment\Domain\Exceptions\AvailabilityConflictException;
use Modules\LeadAppointment\Http\Requests\AvailabilityCreatePageRequest;
use Modules\LeadAppointment\Http\Requests\SaveAvailabilityExceptionRequest;
use Modules\LeadAppointment\Http\Support\AvailabilityExceptionFilterFactory;
use Shared\Application\DTOs\SelectOptionData;
use Shared\Domain\Enums\Permission;

/**
 * The availability screen set. Reads are page visits, never a second copy of a
 * JSON endpoint: the table arrives as props, while the month grid is a
 * client-owned panel that refreshes through `/availability/calendar`.
 *
 * The JSON resource (`/availability-exceptions`) stays the transport for the
 * mobile client and the dashboard's bulk closure; these page routes only add
 * the Inertia visit + redirect contract.
 */
final readonly class AvailabilityPageController
{
    public function __construct(
        private AvailabilityReader $availability,
        private AvailabilityWriter $availabilityWriter,
    ) {}

    public function index(Request $request): InertiaResponse
    {
        $filters = AvailabilityExceptionFilterFactory::fromRequest($request);
        $page = $this->availability->paginateExceptions($filters);
        $timezone = $this->timezone();
        $today = CarbonImmutable::now($timezone);

        return inertia('Availability/Index', [
            'rows' => $page->items(),
            'total' => $page->total(),
            'currentPage' => $page->currentPage(),
            'perPage' => $page->perPage(),
            'rules' => $this->can($request, Permission::ViewAnyAvailabilityRule->value)
                ? $this->availability->rules()
                : [],
            'options' => $this->options(),
            'calendar' => [
                'from' => $today->startOfMonth()->toDateString(),
                'to' => $today->endOfMonth()->toDateString(),
                'today' => $today->toDateString(),
                'timezone' => $timezone,
                'maxDays' => (int) config('lead-appointment.calendar_max_days', 92),
            ],
            'canCreate' => $this->can($request, Permission::CreateAvailabilityException->value),
            'canUpdate' => $this->can($request, Permission::UpdateAvailabilityException->value),
            'canDelete' => $this->can($request, Permission::DeleteAvailabilityException->value),
            'canRestore' => $this->can($request, Permission::RestoreAvailabilityException->value),
            'canExport' => $this->can($request, Permission::ExportAvailabilityException->value),
            'canBulkDelete' => $this->can($request, Permission::BulkDeleteAvailabilityException->value),
            'canBulkRestore' => $this->can($request, Permission::BulkRestoreAvailabilityException->value),
            'canViewCalendar' => $this->can($request, Permission::ViewAnyAvailabilityRule->value),
            'canCreateRule' => $this->can($request, Permission::CreateAvailabilityRule->value),
            'canUpdateRule' => $this->can($request, Permission::UpdateAvailabilityRule->value),
            'canDeleteRule' => $this->can($request, Permission::DeleteAvailabilityRule->value),
        ]);
    }

    public function create(AvailabilityCreatePageRequest $request): InertiaResponse
    {
        return inertia('Availability/Create', [
            'defaults' => [
                'date' => $request->validated('date'),
                'is_available' => false,
            ],
            'timezone' => $this->timezone(),
        ]);
    }

    public function edit(Request $request, string $uuid): InertiaResponse
    {
        $exception = $this->availability->findException($uuid);

        if ($exception === null) {
            abort(404);
        }

        return inertia('Availability/Edit', [
            'exception' => $exception,
            'timezone' => $this->timezone(),
        ]);
    }

    public function store(SaveAvailabilityExceptionRequest $request): RedirectResponse
    {
        return $this->persist($request, null, 'Availability saved for');
    }

    public function update(SaveAvailabilityExceptionRequest $request, string $uuid): RedirectResponse
    {
        return $this->persist($request, $uuid, 'Availability updated for');
    }

    public function destroy(string $uuid): RedirectResponse
    {
        $this->availabilityWriter->deleteException($uuid);

        return back()->with('status', 'The day was removed from the availability calendar.');
    }

    public function restore(string $uuid): RedirectResponse
    {
        try {
            $this->availabilityWriter->restoreException($uuid);
        } catch (AvailabilityConflictException $exception) {
            return back()->withErrors(['date' => $exception->getMessage()]);
        }

        return back()->with('status', 'The day was restored to the availability calendar.');
    }

    private function persist(
        SaveAvailabilityExceptionRequest $request,
        ?string $uuid,
        string $message,
    ): RedirectResponse {
        $data = SaveAvailabilityExceptionData::from($request->validated());

        try {
            $this->availabilityWriter->saveException($data, $uuid);
        } catch (AvailabilityConflictException $exception) {
            return back()->withErrors(['date' => $exception->getMessage()])->withInput();
        }

        return to_route('availability.index')->with('status', $message.' '.$data->date.'.');
    }

    /**
     * @return array{
     *     availability: list<SelectOptionData>,
     *     sources: list<SelectOptionData>,
     *     recordStates: list<SelectOptionData>
     * }
     */
    private function options(): array
    {
        return [
            'availability' => [
                new SelectOptionData('open', 'Open'),
                new SelectOptionData('closed', 'Closed'),
            ],
            'sources' => array_map(
                static fn (AvailabilitySource $source): SelectOptionData => new SelectOptionData(
                    value: $source->value,
                    label: $source->label(),
                ),
                AvailabilitySource::cases(),
            ),
            'recordStates' => [
                new SelectOptionData('active', 'Active'),
                new SelectOptionData('trashed', 'Trashed'),
                new SelectOptionData('all', 'All (incl. trashed)'),
            ],
        ];
    }

    private function timezone(): string
    {
        return Region::from((string) config('lead-appointment.default_region', Region::Houston->value))
            ->timezone();
    }

    private function can(Request $request, string $permission): bool
    {
        return $request->user()?->can($permission) ?? false;
    }
}
