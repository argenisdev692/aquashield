<?php

declare(strict_types=1);

namespace Modules\LeadAppointment\Infrastructure\Persistence\Eloquent\Repositories;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Modules\LeadAppointment\Application\Dtos\AvailabilityExceptionData;
use Modules\LeadAppointment\Application\Dtos\AvailabilityExceptionFilterData;
use Modules\LeadAppointment\Application\Dtos\AvailabilityRuleData;
use Modules\LeadAppointment\Application\Dtos\SaveAvailabilityExceptionData;
use Modules\LeadAppointment\Application\Dtos\SaveAvailabilityRuleData;
use Modules\LeadAppointment\Application\Ports\AvailabilityReader;
use Modules\LeadAppointment\Application\Ports\AvailabilityWriter;
use Modules\LeadAppointment\Domain\Enums\AvailabilitySource;
use Modules\LeadAppointment\Domain\Exceptions\AvailabilityConflictException;
use Modules\LeadAppointment\Domain\Ports\AvailabilityScheduleReader;
use Modules\LeadAppointment\Domain\Ports\HolidayScheduleWriter;
use Modules\LeadAppointment\Domain\ValueObjects\AvailabilityException as AvailabilityExceptionVo;
use Modules\LeadAppointment\Domain\ValueObjects\TimeWindow;
use Modules\LeadAppointment\Infrastructure\Persistence\Eloquent\CalendarLock;
use Modules\LeadAppointment\Infrastructure\Persistence\Eloquent\Models\AvailabilityException;
use Modules\LeadAppointment\Infrastructure\Persistence\Eloquent\Models\AvailabilityRule;

final readonly class EloquentAvailabilityRepository implements AvailabilityReader, AvailabilityScheduleReader, AvailabilityWriter, HolidayScheduleWriter
{
    public function __construct(private CalendarLock $calendarLock) {}

    private const array DAY_LABELS = [
        0 => 'Sunday',
        1 => 'Monday',
        2 => 'Tuesday',
        3 => 'Wednesday',
        4 => 'Thursday',
        5 => 'Friday',
        6 => 'Saturday',
    ];

    public function findActiveException(string $date): ?AvailabilityExceptionVo
    {
        $exception = AvailabilityException::query()
            ->whereDate('date', $date)
            ->first();

        return $exception === null ? null : $this->toExceptionVo($exception);
    }

    /**
     * @return array<string, AvailabilityExceptionVo>
     */
    public function activeExceptionsBetween(string $from, string $to): array
    {
        return AvailabilityException::query()
            ->whereDate('date', '>=', $from)
            ->whereDate('date', '<=', $to)
            ->get()
            ->keyBy(fn (AvailabilityException $exception): string => $exception->date->toDateString())
            ->map(fn (AvailabilityException $exception): AvailabilityExceptionVo => $this->toExceptionVo($exception))
            ->all();
    }

    /**
     * @return list<TimeWindow>
     */
    public function slotsForDay(int $dayOfWeek): array
    {
        return AvailabilityRule::query()
            ->where('day_of_week', $dayOfWeek)
            ->where('is_available', true)
            ->orderBy('start_time')
            ->get()
            ->map(fn (AvailabilityRule $rule): TimeWindow => new TimeWindow(
                $this->time($rule->start_time),
                $this->time($rule->end_time),
            ))
            ->values()
            ->all();
    }

    /**
     * @return array<int, list<TimeWindow>>
     */
    public function slotsGroupedByDay(): array
    {
        return AvailabilityRule::query()
            ->where('is_available', true)
            ->orderBy('start_time')
            ->get()
            ->groupBy('day_of_week')
            ->map(fn ($rules): array => $rules
                ->map(fn (AvailabilityRule $rule): TimeWindow => new TimeWindow(
                    $this->time($rule->start_time),
                    $this->time($rule->end_time),
                ))
                ->values()
                ->all())
            ->all();
    }

    /**
     * @param array<string, string> $holidays date (Y-m-d) => holiday name
     * @return array{purged: int, created: int, skipped: int}
     */
    public function syncHolidays(array $holidays, ?string $purgeFrom = null): array
    {
        return DB::transaction(function () use ($holidays, $purgeFrom): array {
            $this->calendarLock->acquire();

            $dates = array_keys($holidays);
            $existing = $dates === []
                ? collect()
                : AvailabilityException::query()
                    ->whereDate('date', '>=', min($dates))
                    ->whereDate('date', '<=', max($dates))
                    ->get()
                    ->keyBy(fn (AvailabilityException $exception): string => $exception->date->toDateString());

            $created = 0;
            $skipped = 0;

            foreach ($holidays as $date => $name) {
                $exception = $existing->get($date);

                if ($exception === null) {
                    AvailabilityException::query()->create([
                        'date' => $date,
                        'is_available' => false,
                        'start_time' => null,
                        'end_time' => null,
                        'reason' => $name,
                        'source' => AvailabilitySource::Holiday->value,
                    ]);
                    $created++;

                    continue;
                }

                $skipped++;

                if ($exception->source === AvailabilitySource::Holiday) {
                    $exception->fill([
                        'is_available' => false,
                        'start_time' => null,
                        'end_time' => null,
                        'reason' => $name,
                    ])->save();
                }
            }

            $purged = 0;

            if ($purgeFrom !== null) {
                $stale = AvailabilityException::query()
                    ->where('source', AvailabilitySource::Holiday->value)
                    ->whereDate('date', '>=', $purgeFrom)
                    ->get()
                    ->reject(fn (AvailabilityException $exception): bool => in_array($exception->date->toDateString(), $dates, true));

                foreach ($stale as $exception) {
                    $exception->delete();
                }

                $purged = $stale->count();
            }

            return ['purged' => $purged, 'created' => $created, 'skipped' => $skipped];
        });
    }

    /**
     * @return list<AvailabilityRuleData>
     */
    public function rules(): array
    {
        return AvailabilityRule::query()
            ->orderBy('day_of_week')
            ->orderBy('start_time')
            ->limit((int) config('lead-appointment.availability_rules.max_active', 50))
            ->get()
            ->map(fn (AvailabilityRule $rule): AvailabilityRuleData => $this->toRuleData($rule))
            ->values()
            ->all();
    }

    /**
     * @return list<AvailabilityRuleData>
     */
    public function trashedRules(int $limit): array
    {
        return AvailabilityRule::onlyTrashed()
            ->orderBy('day_of_week')
            ->orderBy('start_time')
            ->limit($limit)
            ->get()
            ->map(fn (AvailabilityRule $rule): AvailabilityRuleData => $this->toRuleData($rule))
            ->values()
            ->all();
    }

    public function trashedRulesCount(): int
    {
        return AvailabilityRule::onlyTrashed()->count();
    }

    public function findRule(string $uuid): ?AvailabilityRuleData
    {
        $rule = AvailabilityRule::query()
            ->where('uuid', $uuid)
            ->first();

        return $rule === null ? null : $this->toRuleData($rule);
    }

    public function saveRule(SaveAvailabilityRuleData $data, ?string $uuid = null): AvailabilityRuleData
    {
        return $this->withCalendarLock(function () use ($data, $uuid): AvailabilityRuleData {
            $rule = $uuid === null
                ? new AvailabilityRule
                : AvailabilityRule::query()->where('uuid', $uuid)->firstOrFail();

            $this->assertRuleCapacityAllowed($uuid, $data->dayOfWeek);

            $rule->fill([
                'day_of_week' => $data->dayOfWeek,
                'start_time' => $this->time($data->startTime),
                'end_time' => $this->time($data->endTime),
                'is_available' => $data->isAvailable,
            ]);
            $rule->save();

            return $this->toRuleData($rule);
        });
    }

    public function deleteRule(string $uuid): void
    {
        $this->withCalendarLock(function () use ($uuid): void {
            AvailabilityRule::query()->where('uuid', $uuid)->first()?->delete();
        });
    }

    public function restoreRule(string $uuid): AvailabilityRuleData
    {
        return $this->withCalendarLock(function () use ($uuid): AvailabilityRuleData {
            $rule = AvailabilityRule::onlyTrashed()->where('uuid', $uuid)->firstOrFail();
            $this->assertRuleCapacityAllowed($rule->uuid, $rule->day_of_week);
            $rule->restore();

            return $this->toRuleData($rule);
        });
    }

    /**
     * @param list<string> $uuids
     */
    public function bulkDeleteRules(array $uuids): void
    {
        $this->withCalendarLock(function () use ($uuids): void {
            AvailabilityRule::query()->whereIn('uuid', $uuids)->get()->each(
                static fn (AvailabilityRule $rule) => $rule->delete(),
            );
        });
    }

    /**
     * @param list<string> $uuids
     */
    public function bulkRestoreRules(array $uuids): void
    {
        $this->withCalendarLock(function () use ($uuids): void {
            $rules = AvailabilityRule::onlyTrashed()->whereIn('uuid', $uuids)->get();

            foreach ($rules as $rule) {
                $this->assertRuleCapacityAllowed($rule->uuid, $rule->day_of_week);
                $rule->restore();
            }
        });
    }

    /**
     * @return LengthAwarePaginator<int, AvailabilityExceptionData>
     */
    public function paginateExceptions(AvailabilityExceptionFilterData $filters): LengthAwarePaginator
    {
        return $this->exceptionQuery($filters)
            ->paginate(perPage: $filters->perPage, page: $filters->page)
            ->withQueryString()
            ->through(fn (AvailabilityException $exception): AvailabilityExceptionData => $this->toExceptionData($exception));
    }

    public function countExceptions(AvailabilityExceptionFilterData $filters): int
    {
        return $this->exceptionQuery($filters)->count();
    }

    /**
     * @return iterable<int, AvailabilityExceptionData>
     */
    public function streamExceptions(AvailabilityExceptionFilterData $filters): iterable
    {
        foreach ($this->exceptionQuery($filters)->lazy(500) as $exception) {
            yield $this->toExceptionData($exception);
        }
    }

    /**
     * @return list<AvailabilityExceptionData>
     */
    public function exceptions(?string $from, ?string $to): array
    {
        return AvailabilityException::query()
            ->when($from !== null, fn ($query) => $query->whereDate('date', '>=', $from))
            ->when($to !== null, fn ($query) => $query->whereDate('date', '<=', $to))
            ->orderBy('date')
            ->get()
            ->map(fn (AvailabilityException $exception): AvailabilityExceptionData => $this->toExceptionData($exception))
            ->values()
            ->all();
    }

    public function findException(string $uuid): ?AvailabilityExceptionData
    {
        $exception = AvailabilityException::query()
            ->where('uuid', $uuid)
            ->first();

        return $exception === null ? null : $this->toExceptionData($exception);
    }

    public function findExceptionByDate(string $date): ?AvailabilityExceptionData
    {
        $exception = AvailabilityException::query()
            ->whereDate('date', $date)
            ->first();

        return $exception === null ? null : $this->toExceptionData($exception);
    }

    public function saveException(
        SaveAvailabilityExceptionData $data,
        ?string $uuid = null,
    ): AvailabilityExceptionData {
        try {
            return $this->withCalendarLock(function () use ($data, $uuid): AvailabilityExceptionData {
                $exception = $uuid === null
                    ? new AvailabilityException
                    : AvailabilityException::query()->where('uuid', $uuid)->firstOrFail();

                $exception->fill([
                    'date' => $data->date,
                    'is_available' => $data->isAvailable,
                    'start_time' => $data->startTime === null ? null : $this->time($data->startTime),
                    'end_time' => $data->endTime === null ? null : $this->time($data->endTime),
                    'reason' => $data->reason,
                ]);

                if (! $exception->exists || $exception->source === AvailabilitySource::Holiday) {
                    $exception->source = AvailabilitySource::Manual;
                }

                $exception->save();

                return $this->toExceptionData($exception);
            });
        } catch (UniqueConstraintViolationException) {
            throw AvailabilityConflictException::forDate($data->date);
        }
    }

    public function deleteException(string $uuid): void
    {
        $this->withCalendarLock(function () use ($uuid): void {
            AvailabilityException::query()->where('uuid', $uuid)->first()?->delete();
        });
    }

    public function restoreException(string $uuid): AvailabilityExceptionData
    {
        try {
            return $this->withCalendarLock(function () use ($uuid): AvailabilityExceptionData {
                $exception = AvailabilityException::onlyTrashed()->where('uuid', $uuid)->firstOrFail();
                $exception->restore();

                return $this->toExceptionData($exception);
            });
        } catch (UniqueConstraintViolationException) {
            throw AvailabilityConflictException::forScope();
        }
    }

    /**
     * @param list<string> $uuids
     */
    public function bulkDeleteExceptions(array $uuids): void
    {
        $this->withCalendarLock(function () use ($uuids): void {
            AvailabilityException::query()->whereIn('uuid', $uuids)->get()->each(
                static fn (AvailabilityException $exception) => $exception->delete(),
            );
        });
    }

    /**
     * @param list<string> $uuids
     */
    public function bulkRestoreExceptions(array $uuids): void
    {
        try {
            $this->withCalendarLock(function () use ($uuids): void {
                AvailabilityException::onlyTrashed()->whereIn('uuid', $uuids)->get()->each(
                    static fn (AvailabilityException $exception) => $exception->restore(),
                );
            });
        } catch (UniqueConstraintViolationException) {
            throw AvailabilityConflictException::forScope();
        }
    }

    public function createRangeExceptions(
        SaveAvailabilityExceptionData $data,
        string $from,
        string $to,
    ): int {
        try {
            return $this->withCalendarLock(function () use ($data, $from, $to): int {
                $existingDate = AvailabilityException::query()
                    ->whereBetween('date', [$from, $to])
                    ->value('date');

                if ($existingDate !== null) {
                    throw AvailabilityConflictException::forDate((string) $existingDate);
                }

                $cursor = CarbonImmutable::parse($from);
                $end = CarbonImmutable::parse($to);
                $created = 0;

                while ($cursor->lessThanOrEqualTo($end)) {
                    AvailabilityException::query()->create([
                        'date' => $cursor->toDateString(),
                        'is_available' => $data->isAvailable,
                        'start_time' => $data->startTime,
                        'end_time' => $data->endTime,
                        'reason' => $data->reason,
                        'source' => AvailabilitySource::Manual->value,
                    ]);

                    $created++;
                    $cursor = $cursor->addDay();
                }

                return $created;
            });
        } catch (UniqueConstraintViolationException) {
            throw AvailabilityConflictException::forScope();
        }
    }

    private function assertRuleCapacityAllowed(?string $uuid, int $dayOfWeek): void
    {
        $query = AvailabilityRule::query()
            ->when($uuid !== null, fn (Builder $builder): Builder => $builder->where('uuid', '!=', $uuid));

        if ((clone $query)->where('day_of_week', $dayOfWeek)->count() >= (int) config('lead-appointment.availability_rules.max_per_weekday', 10)) {
            throw AvailabilityConflictException::forWeekdayRuleLimit();
        }

        if ($query->count() >= (int) config('lead-appointment.availability_rules.max_active', 50)) {
            throw AvailabilityConflictException::forTotalRuleLimit();
        }
    }

    /**
     * @template T
     *
     * @param callable(): T $operation
     * @return T
     */
    private function withCalendarLock(callable $operation): mixed
    {
        return DB::transaction(function () use ($operation): mixed {
            $this->calendarLock->acquire();

            return $operation();
        });
    }

    private function time(string $value): string
    {
        return substr($value, 0, 5);
    }

    /**
     * @return Builder<AvailabilityException>
     */
    private function exceptionQuery(AvailabilityExceptionFilterData $filters): Builder
    {
        $sortField = in_array($filters->sortField, AvailabilityExceptionFilterData::SORTABLE, true)
            ? $filters->sortField
            : 'date';

        $search = $filters->search === null
            ? null
            : '%'.addcslashes($filters->search, '\\%_').'%';

        return AvailabilityException::query()
            ->when($search !== null, fn (Builder $query): Builder => $query->where('reason', 'like', $search))
            ->when($filters->availability === 'open', fn (Builder $query): Builder => $query->where('is_available', true))
            ->when($filters->availability === 'closed', fn (Builder $query): Builder => $query->where('is_available', false))
            ->when($filters->source !== null, fn (Builder $query): Builder => $query->where('source', $filters->source))
            ->when($filters->recordState === 'trashed', fn (Builder $query): Builder => $query->onlyTrashed())
            ->when($filters->recordState === 'all', fn (Builder $query): Builder => $query->withTrashed())
            ->when($filters->dateFrom !== null, fn (Builder $query): Builder => $query->whereDate('date', '>=', $filters->dateFrom))
            ->when($filters->dateTo !== null, fn (Builder $query): Builder => $query->whereDate('date', '<=', $filters->dateTo))
            ->orderBy($sortField, $filters->sortOrder === -1 ? 'desc' : 'asc')
            ->orderBy('id');
    }

    private function toExceptionVo(AvailabilityException $exception): AvailabilityExceptionVo
    {
        return new AvailabilityExceptionVo(
            date: $exception->date->toDateString(),
            isAvailable: $exception->is_available,
            startTime: $exception->start_time === null ? null : $this->time($exception->start_time),
            endTime: $exception->end_time === null ? null : $this->time($exception->end_time),
            reason: $exception->reason,
            source: $exception->source,
        );
    }

    private function toRuleData(AvailabilityRule $rule): AvailabilityRuleData
    {
        return new AvailabilityRuleData(
            uuid: $rule->uuid,
            dayOfWeek: $rule->day_of_week,
            dayLabel: self::DAY_LABELS[$rule->day_of_week] ?? (string) $rule->day_of_week,
            startTime: $this->time($rule->start_time),
            endTime: $this->time($rule->end_time),
            isAvailable: $rule->is_available,
        );
    }

    private function toExceptionData(AvailabilityException $exception): AvailabilityExceptionData
    {
        return new AvailabilityExceptionData(
            uuid: $exception->uuid,
            date: $exception->date->toDateString(),
            isAvailable: $exception->is_available,
            startTime: $exception->start_time === null ? null : $this->time($exception->start_time),
            endTime: $exception->end_time === null ? null : $this->time($exception->end_time),
            reason: $exception->reason,
            source: $exception->source->value,
            deletedAt: $exception->deleted_at?->toIso8601String(),
        );
    }
}
