<?php

declare(strict_types=1);

namespace Modules\LeadAppointment\Application\Ports;

use Modules\LeadAppointment\Application\Dtos\AvailabilityExceptionData;
use Modules\LeadAppointment\Application\Dtos\AvailabilityRuleData;
use Modules\LeadAppointment\Application\Dtos\SaveAvailabilityExceptionData;
use Modules\LeadAppointment\Application\Dtos\SaveAvailabilityRuleData;

interface AvailabilityRepository
{
    /**
     * @return list<AvailabilityRuleData>
     */
    public function rules(): array;

    public function findRule(string $uuid): ?AvailabilityRuleData;

    public function saveRule(SaveAvailabilityRuleData $data, ?string $uuid = null): AvailabilityRuleData;

    public function deleteRule(string $uuid): void;

    public function restoreRule(string $uuid): AvailabilityRuleData;

    /**
     * @param list<string> $uuids
     */
    public function bulkDeleteRules(array $uuids): void;

    /**
     * @param list<string> $uuids
     */
    public function bulkRestoreRules(array $uuids): void;

    /**
     * @return list<AvailabilityExceptionData>
     */
    public function exceptions(?string $from, ?string $to): array;

    public function findException(string $uuid): ?AvailabilityExceptionData;

    public function saveException(
        SaveAvailabilityExceptionData $data,
        ?string $uuid = null,
    ): AvailabilityExceptionData;

    public function deleteException(string $uuid): void;

    public function restoreException(string $uuid): AvailabilityExceptionData;

    /**
     * @param list<string> $uuids
     */
    public function bulkDeleteExceptions(array $uuids): void;

    /**
     * @param list<string> $uuids
     */
    public function bulkRestoreExceptions(array $uuids): void;

    public function createRangeExceptions(
        SaveAvailabilityExceptionData $data,
        string $from,
        string $to,
    ): int;
}
