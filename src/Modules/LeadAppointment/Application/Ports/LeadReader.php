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
use Modules\LeadAppointment\Domain\ValueObjects\DateWindow;
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
     * The optional window scopes the board to a period's intake cohort; the
     * region/source scopes mirror the dashboard's URL filters.
     *
     * @return list<LeadBoardColumnData>
     */
    public function board(
        int $perStage = 50,
        ?DateWindow $window = null,
        ?string $region = null,
        ?string $source = null,
    ): array;

    /**
     * Leads captured inside the window. Used for the dashboard's period KPI
     * and its comparison against the previous equal-length period.
     */
    public function countCreatedBetween(
        CarbonImmutable $from,
        CarbonImmutable $to,
        ?string $region = null,
        ?string $source = null,
    ): int;

    /**
     * Leads whose won conversion happened inside the window. `converted_at` is
     * stamped once, the first time the lead reaches Won.
     */
    public function countWonBetween(
        CarbonImmutable $from,
        CarbonImmutable $to,
        ?string $region = null,
        ?string $source = null,
    ): int;

    /**
     * Daily counts of leads captured inside the window, keyed by the company
     * timezone's calendar date (`Y-m-d`). Days with no rows are absent; the
     * caller fills the window so the sparkline keeps a stable daily step.
     *
     * @return array<string, int>
     */
    public function dailyCreatedBetween(
        CarbonImmutable $from,
        CarbonImmutable $to,
        string $timezone,
        ?string $region = null,
        ?string $source = null,
    ): array;

    /**
     * Daily counts of won conversions inside the window, keyed by date.
     *
     * @return array<string, int>
     */
    public function dailyWonBetween(
        CarbonImmutable $from,
        CarbonImmutable $to,
        string $timezone,
        ?string $region = null,
        ?string $source = null,
    ): array;

    /**
     * @return list<LeadActivityData>
     */
    public function activities(string $leadUuid): array;

    /**
     * @return list<AssignableOwnerData>
     */
    public function assignableOwners(): array;

    public function find(string $uuid, bool $withTrashed = false): ?LeadData;

    public function findByExternalId(string $source, string $externalId): ?LeadData;

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
