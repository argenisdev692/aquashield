<?php

declare(strict_types=1);

namespace Modules\LeadAppointment\Application\Ports;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Modules\LeadAppointment\Application\Dtos\AvailabilityExceptionData;
use Modules\LeadAppointment\Application\Dtos\AvailabilityExceptionFilterData;
use Modules\LeadAppointment\Application\Dtos\AvailabilityRuleData;

/**
 * The read side of the availability aggregate: rules, exceptions and the
 * export stream.
 */
interface AvailabilityReader
{
    /**
     * @return LengthAwarePaginator<int, AvailabilityExceptionData>
     */
    public function paginateExceptions(AvailabilityExceptionFilterData $filters): LengthAwarePaginator;

    public function countExceptions(AvailabilityExceptionFilterData $filters): int;

    /**
     * Lazy export stream; same filters as the list.
     *
     * @return iterable<int, AvailabilityExceptionData>
     */
    public function streamExceptions(AvailabilityExceptionFilterData $filters): iterable;

    /**
     * @return list<AvailabilityRuleData>
     */
    public function rules(): array;

    /**
     * Soft-deleted weekly windows, same order as `rules()`.
     *
     * The screen deletes a window with a confirmation dialog; without this read
     * the delete was one-way in the UI even though `restoreRule()` and its route
     * already existed.
     *
     * @return list<AvailabilityRuleData>
     */
    public function trashedRules(int $limit): array;

    public function trashedRulesCount(): int;

    public function findRule(string $uuid): ?AvailabilityRuleData;

    /**
     * @return list<AvailabilityExceptionData>
     */
    public function exceptions(?string $from, ?string $to): array;

    public function findException(string $uuid): ?AvailabilityExceptionData;

    /**
     * The active exception for one calendar date, or null when the day follows
     * the weekly rule. Backs the calendar's "open that day" link, which must
     * land on the edit form when a row already exists — a second active row for
     * the same date is refused by the store action.
     */
    public function findExceptionByDate(string $date): ?AvailabilityExceptionData;
}
