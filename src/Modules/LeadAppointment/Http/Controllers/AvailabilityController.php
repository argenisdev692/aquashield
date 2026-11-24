<?php

declare(strict_types=1);

namespace Modules\LeadAppointment\Http\Controllers;

use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Modules\LeadAppointment\Application\Actions\SyncHolidaysAction;
use Modules\LeadAppointment\Application\Dtos\SaveAvailabilityExceptionData;
use Modules\LeadAppointment\Application\Dtos\SaveAvailabilityRuleData;
use Modules\LeadAppointment\Application\Ports\AvailabilityRepository;
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
        private AvailabilityRepository $availability,
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
            $this->availability->saveRule(SaveAvailabilityRuleData::from($request->validated())),
            201,
        );
    }

    public function updateRule(SaveAvailabilityRuleRequest $request, string $uuid): JsonResponse
    {
        return response()->json(
            $this->availability->saveRule(SaveAvailabilityRuleData::from($request->validated()), $uuid),
        );
    }

    public function destroyRule(string $uuid): JsonResponse
    {
        $this->availability->deleteRule($uuid);

        return response()->json(null, 204);
    }

    public function restoreRule(string $uuid): JsonResponse
    {
        return response()->json($this->availability->restoreRule($uuid));
    }

    public function bulkDeleteRules(Request $request): JsonResponse
    {
        $data = BulkUuidsData::validateAndCreate($request->all());
        $this->availability->bulkDeleteRules($data->uuids);

        return response()->json(null, 204);
    }

    public function bulkRestoreRules(Request $request): JsonResponse
    {
        $data = BulkUuidsData::validateAndCreate($request->all());
        $this->availability->bulkRestoreRules($data->uuids);

        return response()->json(null, 204);
    }

    public function exceptions(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
        ]);

        return response()->json([
            'data' => $this->availability->exceptions(
                $validated['from'] ?? null,
                $validated['to'] ?? null,
            ),
        ]);
    }

    public function storeException(SaveAvailabilityExceptionRequest $request): JsonResponse
    {
        return response()->json(
            $this->availability->saveException(SaveAvailabilityExceptionData::from($request->validated())),
            201,
        );
    }

    public function updateException(SaveAvailabilityExceptionRequest $request, string $uuid): JsonResponse
    {
        return response()->json(
            $this->availability->saveException(SaveAvailabilityExceptionData::from($request->validated()), $uuid),
        );
    }

    public function destroyException(string $uuid): JsonResponse
    {
        $this->availability->deleteException($uuid);

        return response()->json(null, 204);
    }

    public function restoreException(string $uuid): JsonResponse
    {
        return response()->json($this->availability->restoreException($uuid));
    }

    public function bulkDeleteExceptions(Request $request): JsonResponse
    {
        $data = BulkUuidsData::validateAndCreate($request->all());
        $this->availability->bulkDeleteExceptions($data->uuids);

        return response()->json(null, 204);
    }

    public function bulkRestoreExceptions(Request $request): JsonResponse
    {
        $data = BulkUuidsData::validateAndCreate($request->all());
        $this->availability->bulkRestoreExceptions($data->uuids);

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

        $created = $this->availability->createRangeExceptions(
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

    public function calendar(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'from' => ['required', 'date_format:Y-m-d'],
            'to' => ['required', 'date_format:Y-m-d', 'after_or_equal:from'],
            'region' => ['nullable', Rule::enum(Region::class)],
        ]);

        $timezone = Region::from($validated['region'] ?? (string) config('lead-appointment.default_region', Region::Houston->value))->timezone();
        $from = CarbonImmutable::parse((string) $validated['from'], $timezone);
        $to = CarbonImmutable::parse((string) $validated['to'], $timezone);
        $maxDays = (int) config('lead-appointment.calendar_max_days', 92);

        if ($from->diffInDays($to) > $maxDays) {
            return response()->json([
                'message' => "The calendar window is limited to {$maxDays} days.",
            ], 422);
        }

        $days = array_map(
            static fn (ResolvedDay $day): array => [
                'date' => $day->date,
                'is_open' => $day->isOpen,
                'source' => $day->source,
                'reason' => $day->reason,
                'windows' => array_map(
                    static fn (TimeWindow $window): array => ['start' => $window->start, 'end' => $window->end],
                    $day->windows,
                ),
            ],
            array_values($this->resolver->resolveRange($from, $to)),
        );

        return response()->json(['data' => $days]);
    }

    public function syncHolidays(): JsonResponse
    {
        return response()->json($this->syncHolidays->execute());
    }
}
