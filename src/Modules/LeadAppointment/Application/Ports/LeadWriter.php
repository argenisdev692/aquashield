<?php

declare(strict_types=1);

namespace Modules\LeadAppointment\Application\Ports;

use Modules\LeadAppointment\Application\Dtos\CaptureLeadData;
use Modules\LeadAppointment\Application\Dtos\LeadActivityData;
use Modules\LeadAppointment\Application\Dtos\LeadData;
use Modules\LeadAppointment\Application\Dtos\LogLeadActivityData;
use Modules\LeadAppointment\Application\Dtos\UpdateLeadData;
use Modules\LeadAppointment\Domain\Enums\InspectionStatus;
use Modules\LeadAppointment\Domain\Enums\LeadSource;
use Modules\LeadAppointment\Domain\Enums\LeadStatus;
use Modules\LeadAppointment\Domain\ValueObjects\SpamAssessment;

/**
 * The write side of the lead aggregate. Scheduling advances the lead status
 * inside the appointment repository's transaction, not here.
 */
interface LeadWriter
{
    public function create(
        CaptureLeadData $data,
        LeadSource $source,
        ?string $duplicateOfUuid,
        SpamAssessment $spam,
        ?string $sourceDetail = null,
        ?string $externalSource = null,
        ?string $externalId = null,
        ?int $createdBy = null,
    ): LeadData;

    public function update(string $uuid, UpdateLeadData $data): LeadData;

    public function updateStatus(
        string $uuid,
        LeadStatus $status,
        ?string $inspectionDate = null,
        ?string $inspectionTime = null,
        ?InspectionStatus $inspectionStatus = null,
    ): LeadData;

    public function logActivity(string $leadUuid, LogLeadActivityData $data, ?int $userId): LeadActivityData;

    public function merge(string $targetUuid, string $duplicateUuid): LeadData;

    public function delete(string $uuid): void;

    public function restore(string $uuid): LeadData;

    /**
     * @param list<string> $uuids
     */
    public function bulkDelete(array $uuids): void;

    /**
     * @param list<string> $uuids
     */
    public function bulkRestore(array $uuids): void;
}
