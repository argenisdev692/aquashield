<?php

declare(strict_types=1);

namespace Modules\LeadAppointment\Http\Controllers;

use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Modules\LeadAppointment\Application\Actions\SyncHolidaysAction;
use Modules\LeadAppointment\Application\Dtos\AvailabilityCalendarData;
use Modules\LeadAppointment\Application\Dtos\AvailabilityDayData;
use Modules\LeadAppointment\Application\Dtos\AvailabilityWindowFilterData;
use Modules\LeadAppointment\Application\Dtos\SaveAvailabilityExceptionData;
use Modules\LeadAppointment\Application\Dtos\SaveAvailabilityRuleData;
use Modules\LeadAppointment\Application\Ports\AvailabilityReader;
use Modules\LeadAppointment\Application\Ports\AvailabilityWriter;
use Modules\LeadAppointment\Domain\Enums\Region;
use Modules\LeadAppointment\Domain\Services\AvailabilityResolver;
use Modules\LeadAppointment\Domain\ValueObjects\ResolvedDay;
use Modules\LeadAppointment\Domain\ValueObjects\TimeWindow;
use Modules\LeadAppointment\Http\Requests\BulkRangeExceptionRequest;
use Modules\LeadAppointment\Http\Requests\SaveAvailabilityExceptionRequest;
use Modules\LeadAppointment\Http\Requests\SaveAvailabilityRuleRequest;
use Shared\Application\DTOs\BulkUuidsData;

final readonly class AvailabilityController
{
    public function __construct(
        private AvailabilityReader $availability,
        private AvailabilityWriter $availabilityWriter,
        private AvailabilityResolver $resolver,
        private SyncHolidaysAction $syncHolidays,
    ) {}

    public function rules(): JsonResponse
    {
        return response()->json([
            'data' => $this->availability->rules(),
        ]);
    }

    public function storeRule(SaveAvailabilityRuleRequest $request): JsonResponse
    {
        return response()->json(
            $this->availabilityWriter->saveRule(SaveAvailabilityRuleData::from($request->validated())),
            201,
        );
    }

    public function updateRule(SaveAvailabilityRuleRequest $request, string $uuid): JsonResponse
    {
        return response()->json(
            $this->availabilityWriter->saveRule(SaveAvailabilityRuleData::from($request->validated()), $uuid),
        );
    }

    public function destroyRule(string $uuid): JsonResponse
    {
        $this->availabilityWriter->deleteRule($uuid);

        return response()->json(null, 204);
    }

    public function restoreRule(string $uuid): JsonResponse
    {
        return response()->json($this->availabilityWriter->restoreRule($uuid));
    }

    public function bulkDeleteRules(Request $request): JsonResponse
    {
        $data = BulkUuidsData::validateAndCreate($request->all());
        $this->availabilityWriter->bulkDeleteRules($data->uuids);

        return response()->json(null, 204);
    }

    public function bulkRestoreRules(Request $request): JsonResponse
    {
        $data = BulkUuidsData::validateAndCreate($request->all());
        $this->availabilityWriter->bulkRestoreRules($data->uuids);

        return response()->json(null, 204);
    }

    public function exceptions(Request $request): JsonResponse
    {
        [, $from, $to] = $this->window($request);

        return response()->json([
            'data' => $this->availability->exceptions($from, $to),
        ]);
    }

    public function storeException(SaveAvailabilityExceptionRequest $request): JsonResponse
    {
        return response()->json(
            $this->availabilityWriter->saveException(SaveAvailabilityExceptionData::from($request->validated())),
            201,
        );
    }

    public function updateException(SaveAvailabilityExceptionRequest $request, string $uuid): JsonResponse
    {
        return response()->json(
            $this->availabilityWriter->saveException(SaveAvailabilityExceptionData::from($request->validated()), $uuid),
        );
    }

    public function destroyException(string $uuid): JsonResponse
    {
        $this->availabilityWriter->deleteException($uuid);

        return response()->json(null, 204);
    }

    public function restoreException(string $uuid): JsonResponse
    {
        return response()->json($this->availabilityWriter->restoreException($uuid));
    }

    public function bulkDeleteExceptions(Request $request): JsonResponse
    {
        $data = BulkUuidsData::validateAndCreate($request->all());
        $this->availabilityWriter->bulkDeleteExceptions($data->uuids);

        return response()->json(null, 204);
    }

    public function bulkRestoreExceptions(Request $request): JsonResponse
    {
        $data = BulkUuidsData::validateAndCreate($request->all());
        $this->availabilityWriter->bulkRestoreExceptions($data->uuids);

        return response()->json(null, 204);
    }

    public function bulkRange(BulkRangeExceptionRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $from = CarbonImmutable::parse((string) $validated['from']);
        $to = CarbonImmutable::parse((string) $validated['to']);
        $maxDays = (int) config('lead-appointment.calendar_max_days', 92);

        if ($from->diffInDays($to) > $maxDays) {
            return response()->json([
                'message' => "A bulk closure is limited to {$maxDays} days.",
            ], 422);
        }

        $created = $this->availabilityWriter->createRangeExceptions(
            new SaveAvailabilityExceptionData(
                date: $from->toDateString(),
                isAvailable: (bool) ($validated['is_available'] ?? false),
                startTime: $validated['start_time'] ?? null,
                endTime: $validated['end_time'] ?? null,
                reason: $validated['reason'] ?? null,
            ),
            $from->toDateString(),
            $to->toDateString(),
        );

        return response()->json(['created' => $created], 201);
    }

    public function calendar(Request $request): AvailabilityCalendarData
    {
        [, $from, $to, $timezone] = $this->window($request);

        $days = array_map(
            static fn (ResolvedDay $day): AvailabilityDayData => new AvailabilityDayData(
                date: $day->date,
                isOpen: $day->isOpen,
                source: $day->source,
                reason: $day->reason,
                windows: array_map(
                    static fn (TimeWindow $window): array => ['start' => $window->start, 'end' => $window->end],
                    $day->windows,
                ),
            ),
            array_values($this->resolver->resolveRange(
                CarbonImmutable::parse($from, $timezone),
                CarbonImmutable::parse($to, $timezone),
            )),
        );

        return new AvailabilityCalendarData($days);
    }

    /**
     * @return array{0: AvailabilityWindowFilterData, 1: string, 2: string, 3: string}
     */
    private function window(Request $request): array
    {
        $filters = AvailabilityWindowFilterData::validateAndCreate($request->query());
        $timezone = Region::from(
            $filters->region ?? (string) config('lead-appointment.default_region', Region::Houston->value),
        )->timezone();

        $now = CarbonImmutable::now($timezone);
        $from = $filters->from ?? $now->startOfMonth()->toDateString();
        $to = $filters->to ?? $now->endOfMonth()->toDateString();
        $maxDays = (int) config('lead-appointment.calendar_max_days', 92);

        if (CarbonImmutable::parse($from)->diffInDays(CarbonImmutable::parse($to)) > $maxDays) {
            throw ValidationException::withMessages([
                'to' => "The availability window is limited to {$maxDays} days.",
            ]);
        }

        return [$filters, $from, $to, $timezone];
    }

    public function syncHolidays(): JsonResponse
    {
        // Explicit 200: the command is idempotent, not a resource creation, and
        // the POST default of a Responsable Data object would be 201.
        return response()->json($this->syncHolidays->execute());
    }
}
