<?php

declare(strict_types=1);

namespace Modules\LeadAppointment\Application\Ports;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Modules\LeadAppointment\Application\Dtos\AssignableOwnerData;
use Modules\LeadAppointment\Application\Dtos\LeadActivityData;
use Modules\LeadAppointment\Application\Dtos\LeadBoardColumnData;
use Modules\LeadAppointment\Application\Dtos\LeadData;
use Modules\LeadAppointment\Application\Dtos\LeadFilterData;
use Modules\LeadAppointment\Application\Dtos\LeadListItemData;
use Modules\LeadAppointment\Application\Dtos\LeadSourceReportData;
use Modules\LeadAppointment\Domain\ValueObjects\DuplicateCandidate;

/**
 * The read side of the lead aggregate: lists, board, counters, timeline,
 * duplicate lookup, export stream and the source report.
 */
interface LeadReader
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

    /**
     * @return list<DuplicateCandidate>
     */
    public function findDuplicateCandidates(string $email, ?string $phone, string $address, int $windowDays): array;

    public function count(LeadFilterData $filters): int;

    /**
     * Lazy export stream; same filters as the list, never a materialised array.
     *
     * @return iterable<int, LeadListItemData>
     */
    public function stream(LeadFilterData $filters): iterable;

    public function sourceReport(string $from, string $to): LeadSourceReportData;
}
