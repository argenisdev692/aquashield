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

    public function findRule(string $uuid): ?AvailabilityRuleData;

    /**
     * @return list<AvailabilityExceptionData>
     */
    public function exceptions(?string $from, ?string $to): array;

    public function findException(string $uuid): ?AvailabilityExceptionData;
}
