<?php

declare(strict_types=1);

namespace Modules\LeadAppointment\Application\Ports;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Modules\LeadAppointment\Application\Dtos\AssignableOwnerData;
use Modules\LeadAppointment\Application\Dtos\CaptureLeadData;
use Modules\LeadAppointment\Application\Dtos\LeadActivityData;
use Modules\LeadAppointment\Application\Dtos\LeadBoardColumnData;
use Modules\LeadAppointment\Application\Dtos\LeadData;
use Modules\LeadAppointment\Application\Dtos\LeadFilterData;
use Modules\LeadAppointment\Application\Dtos\LeadListItemData;
use Modules\LeadAppointment\Application\Dtos\LeadSourceReportData;
use Modules\LeadAppointment\Application\Dtos\LogLeadActivityData;
use Modules\LeadAppointment\Application\Dtos\UpdateLeadData;
use Modules\LeadAppointment\Domain\Enums\InspectionStatus;
use Modules\LeadAppointment\Domain\Enums\LeadSource;
use Modules\LeadAppointment\Domain\Enums\LeadStatus;
use Modules\LeadAppointment\Domain\ValueObjects\DuplicateCandidate;
use Modules\LeadAppointment\Domain\ValueObjects\SpamAssessment;

interface LeadRepository
{
    /**
     * @return LengthAwarePaginator<int, LeadListItemData>
     */
    public function paginate(LeadFilterData $filters): LengthAwarePaginator;

    /**
     * Server-grouped pipeline columns. Each column carries its true total and
     * at most `$perStage` cards, so the board never paginates in the browser.
     *
     * @return list<LeadBoardColumnData>
     */
    public function board(int $perStage = 50): array;

    /**
     * Leads captured inside the window. Used for the dashboard's monthly KPI
     * and its month-over-month comparison.
     */
    public function countCreatedBetween(CarbonImmutable $from, CarbonImmutable $to): int;

    /**
     * Leads whose won conversion happened inside the window. `converted_at` is
     * stamped once, the first time the lead reaches Won.
     */
    public function countWonBetween(CarbonImmutable $from, CarbonImmutable $to): int;

    /**
     * @return list<LeadActivityData>
     */
    public function activities(string $leadUuid): array;

    /**
     * @return list<AssignableOwnerData>
     */
    public function assignableOwners(): array;

    public function find(string $uuid, bool $withTrashed = false): ?LeadData;

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

    public function markInspectionScheduled(string $uuid): void;

    public function logActivity(string $leadUuid, LogLeadActivityData $data, ?int $userId): LeadActivityData;

    /**
     * @return list<DuplicateCandidate>
     */
    public function findDuplicateCandidates(string $email, ?string $phone, string $address, int $windowDays): array;

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

    public function count(LeadFilterData $filters): int;

    /**
     * Lazy export stream; same filters as the list, never a materialised array.
     *
     * @return iterable<int, LeadListItemData>
     */
    public function stream(LeadFilterData $filters): iterable;

    public function sourceReport(string $from, string $to): LeadSourceReportData;
}
