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
use Modules\LeadAppointment\Infrastructure\Persistence\Eloquent\Models\AvailabilityException;
use Modules\LeadAppointment\Infrastructure\Persistence\Eloquent\Models\AvailabilityRule;

final readonly class EloquentAvailabilityRepository implements AvailabilityReader, AvailabilityScheduleReader, AvailabilityWriter, HolidayScheduleWriter
{
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
            $purged = 0;

            if ($purgeFrom !== null) {
                $purged = AvailabilityException::query()
                    ->where('source', AvailabilitySource::Holiday->value)
                    ->whereDate('date', '>=', $purgeFrom)
                    ->delete();
            }

            $created = 0;
            $skipped = 0;

            foreach ($holidays as $date => $name) {
                if (AvailabilityException::query()->whereDate('date', $date)->exists()) {
                    $skipped++;

                    continue;
                }

                AvailabilityException::query()->create([
                    'date' => $date,
                    'is_available' => false,
                    'start_time' => null,
                    'end_time' => null,
                    'reason' => $name,
                    'source' => AvailabilitySource::Holiday->value,
                ]);

                $created++;
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
            ->get()
            ->map(fn (AvailabilityRule $rule): AvailabilityRuleData => $this->toRuleData($rule))
            ->values()
            ->all();
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
        $rule = $uuid === null
            ? new AvailabilityRule
            : AvailabilityRule::query()->where('uuid', $uuid)->firstOrFail();

        $rule->fill([
            'day_of_week' => $data->dayOfWeek,
            'start_time' => $this->time($data->startTime),
            'end_time' => $this->time($data->endTime),
            'is_available' => $data->isAvailable,
        ]);
        $rule->save();

        return $this->findRule($rule->uuid) ?? throw new \RuntimeException('Availability rule was not persisted.');
    }

    public function deleteRule(string $uuid): void
    {
        AvailabilityRule::query()->where('uuid', $uuid)->first()?->delete();
    }

    public function restoreRule(string $uuid): AvailabilityRuleData
    {
        $rule = AvailabilityRule::onlyTrashed()->where('uuid', $uuid)->firstOrFail();
        $rule->restore();

        return $this->findRule($rule->uuid) ?? throw new \RuntimeException('Availability rule was not restored.');
    }

    /**
     * @param list<string> $uuids
     */
    public function bulkDeleteRules(array $uuids): void
    {
        AvailabilityRule::query()->whereIn('uuid', $uuids)->get()->each(
            static fn (AvailabilityRule $rule) => $rule->delete(),
        );
    }

    /**
     * @param list<string> $uuids
     */
    public function bulkRestoreRules(array $uuids): void
    {
        AvailabilityRule::onlyTrashed()->whereIn('uuid', $uuids)->get()->each(
            static fn (AvailabilityRule $rule) => $rule->restore(),
        );
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

    public function saveException(
        SaveAvailabilityExceptionData $data,
        ?string $uuid = null,
    ): AvailabilityExceptionData {
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

        if (! $exception->exists) {
            $exception->source = AvailabilitySource::Manual;
        }

        try {
            $exception->save();
        } catch (UniqueConstraintViolationException) {
            throw AvailabilityConflictException::forDate($data->date);
        }

        return $this->findException($exception->uuid)
            ?? throw new \RuntimeException('Availability exception was not persisted.');
    }

    public function deleteException(string $uuid): void
    {
        AvailabilityException::query()->where('uuid', $uuid)->first()?->delete();
    }

    public function restoreException(string $uuid): AvailabilityExceptionData
    {
        $exception = AvailabilityException::onlyTrashed()->where('uuid', $uuid)->firstOrFail();

        try {
            $exception->restore();
        } catch (UniqueConstraintViolationException) {
            throw AvailabilityConflictException::forScope();
        }

        return $this->findException($exception->uuid)
            ?? throw new \RuntimeException('Availability exception was not restored.');
    }

    /**
     * @param list<string> $uuids
     */
    public function bulkDeleteExceptions(array $uuids): void
    {
        AvailabilityException::query()->whereIn('uuid', $uuids)->get()->each(
            static fn (AvailabilityException $exception) => $exception->delete(),
        );
    }

    /**
     * @param list<string> $uuids
     */
    public function bulkRestoreExceptions(array $uuids): void
    {
        AvailabilityException::onlyTrashed()->whereIn('uuid', $uuids)->get()->each(
            static fn (AvailabilityException $exception) => $exception->restore(),
        );
    }

    public function createRangeExceptions(
        SaveAvailabilityExceptionData $data,
        string $from,
        string $to,
    ): int {
        try {
            return DB::transaction(function () use ($data, $from, $to): int {
                $cursor = CarbonImmutable::parse($from);
                $end = CarbonImmutable::parse($to);
                $created = 0;

                while ($cursor->lessThanOrEqualTo($end)) {
                    // `updateOrCreate` compares the raw `date` column, and SQLite stores
                    // the cast value as a datetime string; `whereDate` is the portable
                    // match. The unique expression index still protects the write.
                    $exception = AvailabilityException::query()
                        ->whereDate('date', $cursor->toDateString())
                        ->first();

                    if ($exception === null) {
                        $exception = new AvailabilityException([
                            'date' => $cursor->toDateString(),
                        ]);
                    }

                    $exception->fill([
                        'is_available' => $data->isAvailable,
                        'start_time' => $data->startTime,
                        'end_time' => $data->endTime,
                        'reason' => $data->reason,
                        'source' => AvailabilitySource::Manual->value,
                    ]);
                    $exception->save();

                    $created++;
                    $cursor = $cursor->addDay();
                }

                return $created;
            });
        } catch (UniqueConstraintViolationException) {
            throw AvailabilityConflictException::forScope();
        }
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
