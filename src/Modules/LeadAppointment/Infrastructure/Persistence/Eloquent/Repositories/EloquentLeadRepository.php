<?php

declare(strict_types=1);

namespace Modules\LeadAppointment\Infrastructure\Persistence\Eloquent\Repositories;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Modules\LeadAppointment\Application\Dtos\AssignableOwnerData;
use Modules\LeadAppointment\Application\Dtos\CaptureLeadData;
use Modules\LeadAppointment\Application\Dtos\LeadActivityData;
use Modules\LeadAppointment\Application\Dtos\LeadBoardColumnData;
use Modules\LeadAppointment\Application\Dtos\LeadCardData;
use Modules\LeadAppointment\Application\Dtos\LeadData;
use Modules\LeadAppointment\Application\Dtos\LeadFilterData;
use Modules\LeadAppointment\Application\Dtos\LeadListItemData;
use Modules\LeadAppointment\Application\Dtos\LeadSourceReportData;
use Modules\LeadAppointment\Application\Dtos\LeadSourceReportRowData;
use Modules\LeadAppointment\Application\Dtos\LogLeadActivityData;
use Modules\LeadAppointment\Application\Dtos\UpdateLeadData;
use Modules\LeadAppointment\Application\Ports\LeadReader;
use Modules\LeadAppointment\Application\Ports\LeadWriter;
use Modules\LeadAppointment\Domain\Enums\InspectionStatus;
use Modules\LeadAppointment\Domain\Enums\LeadSource;
use Modules\LeadAppointment\Domain\Enums\LeadStatus;
use Modules\LeadAppointment\Domain\Enums\Region;
use Modules\LeadAppointment\Domain\Exceptions\LeadHasAppointmentsException;
use Modules\LeadAppointment\Domain\Exceptions\LeadRestoreConflictException;
use Modules\LeadAppointment\Domain\Services\StatusTransition;
use Modules\LeadAppointment\Domain\ValueObjects\DateWindow;
use Modules\LeadAppointment\Domain\ValueObjects\DuplicateCandidate;
use Modules\LeadAppointment\Domain\ValueObjects\PhoneNumber;
use Modules\LeadAppointment\Domain\ValueObjects\SpamAssessment;
use Modules\LeadAppointment\Infrastructure\Persistence\Eloquent\Models\Appointment;
use Modules\LeadAppointment\Infrastructure\Persistence\Eloquent\Models\Lead;
use Modules\LeadAppointment\Infrastructure\Persistence\Eloquent\Models\LeadActivity;
use Modules\Users\Enums\UserStatus;

final readonly class EloquentLeadRepository implements LeadReader, LeadWriter
{
    public function __construct(
        private StatusTransition $transitions,
    ) {}

    /**
     * @return LengthAwarePaginator<int, LeadListItemData>
     */
    public function paginate(LeadFilterData $filters): LengthAwarePaginator
    {
        return Lead::query()
            ->applyFilters($filters)
            ->with(['owner:id,uuid,first_name,last_name'])
            ->select([
                'id', 'uuid', 'first_name', 'last_name', 'email', 'phone', 'city', 'state', 'region',
                'source', 'status', 'owner_id', 'is_emergency', 'is_spam',
                'last_contacted_at', 'created_at', 'deleted_at',
            ])
            ->paginate(perPage: $filters->perPage, page: $filters->page)
            ->withQueryString()
            ->through(fn (Lead $lead): LeadListItemData => $this->toListItem($lead));
    }

    /**
     * @return list<LeadBoardColumnData>
     */
    public function board(
        int $perStage = 25,
        ?DateWindow $window = null,
        ?string $region = null,
        ?string $source = null,
    ): array {
        // One grouped count instead of one COUNT per stage. The card queries
        // stay per-stage because each column has its own LIMIT.
        $totals = Lead::query()
            ->selectRaw('status, COUNT(*) as aggregate')
            ->when($window !== null, fn ($query) => $query->whereBetween('created_at', [$window->start->utc(), $window->end->utc()]))
            ->when($region !== null, fn ($query) => $query->where('region', $region))
            ->when($source !== null, fn ($query) => $query->where('source', $source))
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $columns = [];

        foreach (LeadStatus::cases() as $status) {
            $leads = Lead::query()
                ->where('status', $status->value)
                ->when($window !== null, fn ($query) => $query->whereBetween('created_at', [$window->start->utc(), $window->end->utc()]))
                ->when($region !== null, fn ($query) => $query->where('region', $region))
                ->when($source !== null, fn ($query) => $query->where('source', $source))
                ->select([
                    'id', 'uuid', 'first_name', 'last_name', 'phone', 'city', 'state', 'region',
                    'source', 'status', 'is_emergency', 'is_spam', 'last_contacted_at', 'created_at',
                ])
                ->orderByDesc('created_at')
                ->with('latestAppointment:id,lead_id,starts_at,ends_at,timezone,status')
                ->limit($perStage)
                ->get()
                ->map(fn (Lead $lead): LeadCardData => $this->toCardData($lead))
                ->values()
                ->all();

            $columns[] = new LeadBoardColumnData(
                status: $status->value,
                label: $status->label(),
                total: (int) ($totals[$status->value] ?? 0),
                leads: $leads,
            );
        }

        return $columns;
    }

    public function countCreatedBetween(
        CarbonImmutable $from,
        CarbonImmutable $to,
        ?string $region = null,
        ?string $source = null,
    ): int {
        return Lead::query()
            ->whereBetween('created_at', [$from->utc(), $to->utc()])
            ->when($region !== null, fn ($query) => $query->where('region', $region))
            ->when($source !== null, fn ($query) => $query->where('source', $source))
            ->count();
    }

    public function countWonBetween(
        CarbonImmutable $from,
        CarbonImmutable $to,
        ?string $region = null,
        ?string $source = null,
    ): int {
        return Lead::query()
            ->whereNotNull('converted_at')
            ->whereBetween('converted_at', [$from->utc(), $to->utc()])
            ->when($region !== null, fn ($query) => $query->where('region', $region))
            ->when($source !== null, fn ($query) => $query->where('source', $source))
            ->count();
    }

    /**
     * @return array<string, int>
     */
    public function dailyCreatedBetween(
        CarbonImmutable $from,
        CarbonImmutable $to,
        string $timezone,
        ?string $region = null,
        ?string $source = null,
    ): array {
        return Lead::query()
            ->whereBetween('created_at', [$from->utc(), $to->utc()])
            ->when($region !== null, fn ($query) => $query->where('region', $region))
            ->when($source !== null, fn ($query) => $query->where('source', $source))
            ->pluck('created_at')
            ->map(
                static fn (mixed $createdAt): string => CarbonImmutable::parse((string) $createdAt)
                    ->setTimezone($timezone)
                    ->toDateString(),
            )
            ->countBy()
            ->all();
    }

    /**
     * @return array<string, int>
     */
    public function dailyWonBetween(
        CarbonImmutable $from,
        CarbonImmutable $to,
        string $timezone,
        ?string $region = null,
        ?string $source = null,
    ): array {
        return Lead::query()
            ->whereNotNull('converted_at')
            ->whereBetween('converted_at', [$from->utc(), $to->utc()])
            ->when($region !== null, fn ($query) => $query->where('region', $region))
            ->when($source !== null, fn ($query) => $query->where('source', $source))
            ->pluck('converted_at')
            ->map(
                static fn (mixed $convertedAt): string => CarbonImmutable::parse((string) $convertedAt)
                    ->setTimezone($timezone)
                    ->toDateString(),
            )
            ->countBy()
            ->all();
    }

    /**
     * @return list<LeadActivityData>
     */
    public function activities(string $leadUuid): array
    {
        $leadId = Lead::query()->where('uuid', $leadUuid)->value('id');

        if ($leadId === null) {
            return [];
        }

        return LeadActivity::query()
            ->with('user:id,first_name,last_name')
            ->where('lead_id', $leadId)
            ->orderByDesc('occurred_at')
            ->limit(50)
            ->get()
            ->map(fn (LeadActivity $activity): LeadActivityData => $this->toActivityData($activity))
            ->values()
            ->all();
    }

    /**
     * @return list<AssignableOwnerData>
     */
    public function assignableOwners(): array
    {
        return User::query()
            ->where('status', UserStatus::Active->value)
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->limit(500)
            ->get(['uuid', 'first_name', 'last_name'])
            ->map(static fn (User $user): AssignableOwnerData => new AssignableOwnerData(
                uuid: $user->uuid,
                name: trim($user->first_name.' '.$user->last_name),
            ))
            ->values()
            ->all();
    }

    public function find(string $uuid, bool $withTrashed = false): ?LeadData
    {
        $query = Lead::query()
            ->with([
                'owner:id,uuid,first_name,last_name',
                'duplicateOf:id,uuid',
                'latestAppointment:id,lead_id,starts_at,ends_at,timezone,status',
            ])
            ->where('uuid', $uuid);

        if ($withTrashed) {
            $query->withTrashed();
        }

        $lead = $query->first();

        return $lead === null ? null : $this->toData($lead);
    }

    public function create(
        CaptureLeadData $data,
        LeadSource $source,
        ?string $duplicateOfUuid,
        SpamAssessment $spam,
        ?string $sourceDetail = null,
        ?string $externalSource = null,
        ?string $externalId = null,
        ?int $createdBy = null,
    ): LeadData {
        $attribution = array_filter([
            'source_page' => $data->sourcePage,
            'utm_source' => $data->utmSource,
            'utm_medium' => $data->utmMedium,
            'utm_campaign' => $data->utmCampaign,
            'utm_content' => $data->utmContent,
            'utm_term' => $data->utmTerm,
        ], static fn (mixed $value): bool => $value !== null && $value !== '');

        $lead = Lead::query()->create([
            'first_name' => $data->firstName,
            'last_name' => $data->lastName,
            'company_name' => $data->companyName,
            'email' => $data->email === null ? null : mb_strtolower(trim($data->email)),
            'phone' => PhoneNumber::fromString($data->phone)?->value,
            'address' => $data->address,
            'address_2' => $data->address2,
            'city' => $data->city,
            'state' => $data->state,
            'zip_code' => $data->zipCode,
            'country' => 'US',
            'insurance_property' => $data->insuranceProperty,
            'region' => $data->region,
            'source' => $source->value,
            'source_detail' => $sourceDetail,
            'external_source' => $externalSource,
            'external_id' => $externalId,
            'status' => LeadStatus::New->value,
            'owner_id' => $data->ownerUuid === null
                ? null
                : User::query()->where('uuid', $data->ownerUuid)->value('id'),
            'created_by' => $createdBy,
            'duplicate_of_id' => $duplicateOfUuid === null
                ? null
                : Lead::query()->where('uuid', $duplicateOfUuid)->value('id'),
            'is_emergency' => $data->isEmergency,
            'preferred_language' => $data->preferredLanguage,
            'damage_description' => $data->damageDescription,
            'message' => $data->message,
            'intent_to_claim' => $data->intentToClaim,
            'follow_up_date' => $data->followUpDate,
            'inspection_date' => $data->inspectionDate,
            'inspection_time' => $data->inspectionTime,
            'inspection_status' => $data->inspectionStatus?->value,
            'additional_note' => $data->additionalNote,
            'latitude' => $data->latitude,
            'longitude' => $data->longitude,
            'is_spam' => $spam->isSpam,
            'spam_score' => $spam->score,
            'spam_reasons' => $spam->reasons === [] ? null : $spam->reasons,
            'attribution' => $attribution === [] ? null : $attribution,
            'sms_consent' => $data->smsConsent,
            'sms_consent_at' => $data->smsConsent ? CarbonImmutable::now() : null,
            'sms_consent_ip' => $data->smsConsent ? $data->ip : null,
            'sms_consent_user_agent' => $data->smsConsent ? $data->userAgent : null,
            'sms_consent_source' => $data->smsConsent && $source === LeadSource::Website ? 'website' : null,
            'consent_text_version' => $data->smsConsent ? $data->consentTextVersion : null,
            'notes' => $data->notes,
        ]);

        return $this->find($lead->uuid) ?? throw new \RuntimeException('Lead was not persisted.');
    }

    public function update(string $uuid, UpdateLeadData $data): LeadData
    {
        $lead = Lead::query()->where('uuid', $uuid)->firstOrFail();

        $ownerId = $data->ownerUuid === null
            ? null
            : User::query()->where('uuid', $data->ownerUuid)->value('id');

        $lead->fill([
            'first_name' => $data->firstName,
            'last_name' => $data->lastName,
            'company_name' => $data->companyName,
            'email' => $data->email === null ? null : mb_strtolower(trim($data->email)),
            'phone' => PhoneNumber::fromString($data->phone)?->value,
            'address' => $data->address,
            'address_2' => $data->address2,
            'city' => $data->city,
            'state' => $data->state,
            'zip_code' => $data->zipCode,
            'insurance_property' => $data->insuranceProperty,
            'region' => $data->region,
            'owner_id' => $ownerId,
            'is_emergency' => $data->isEmergency,
            'preferred_language' => $data->preferredLanguage,
            'damage_description' => $data->damageDescription,
            'message' => $data->message,
            'intent_to_claim' => $data->intentToClaim,
            'follow_up_date' => $data->followUpDate,
            'inspection_date' => $data->inspectionDate,
            'inspection_time' => $data->inspectionTime,
            'inspection_status' => $data->inspectionStatus?->value,
            'additional_note' => $data->additionalNote,
            'insurance_carrier' => $data->insuranceCarrier,
            'claim_number' => $data->claimNumber,
            'latitude' => $data->latitude,
            'longitude' => $data->longitude,
            'notes' => $data->notes,
        ]);

        $lead->save();

        return $this->find($lead->uuid) ?? throw new \RuntimeException('Lead was not persisted.');
    }

    public function updateStatus(
        string $uuid,
        LeadStatus $status,
        ?string $inspectionDate = null,
        ?string $inspectionTime = null,
        ?InspectionStatus $inspectionStatus = null,
        ?LogLeadActivityData $activity = null,
        ?int $actorId = null,
    ): LeadData {
        $lead = DB::transaction(function () use ($uuid, $status, $inspectionDate, $inspectionTime, $inspectionStatus, $activity, $actorId): Lead {
            $lead = Lead::query()->where('uuid', $uuid)->lockForNoKeyUpdate()->firstOrFail();

            if ($lead->status !== $status) {
                $this->transitions->assertLead($lead->status, $status);
            }

            $lead->status = $status;

            if ($inspectionDate !== null) {
                $lead->inspection_date = CarbonImmutable::parse($inspectionDate)->startOfDay();
            }

            if ($inspectionTime !== null) {
                $lead->inspection_time = $inspectionTime;
            }

            if ($inspectionStatus !== null) {
                $lead->inspection_status = $inspectionStatus;
            }

            if ($status === LeadStatus::Won && $lead->converted_at === null) {
                $lead->converted_at = CarbonImmutable::now();
            }

            $lead->save();

            if ($activity !== null) {
                $this->createActivity($lead, $activity, $actorId);
            }

            return $lead;
        });

        return $this->find($lead->uuid) ?? throw new \RuntimeException('Lead was not persisted.');
    }

    public function logActivity(string $leadUuid, LogLeadActivityData $data, ?int $userId): LeadActivityData
    {
        return DB::transaction(function () use ($leadUuid, $data, $userId): LeadActivityData {
            $lead = Lead::query()->where('uuid', $leadUuid)->firstOrFail();

            return $this->toActivityData($this->createActivity($lead, $data, $userId)->load('user:id,first_name,last_name'));
        });
    }

    private function createActivity(Lead $lead, LogLeadActivityData $data, ?int $userId): LeadActivity
    {
        $occurredAt = $data->occurredAt === null
            ? CarbonImmutable::now()
            : CarbonImmutable::parse($data->occurredAt);

        $activity = LeadActivity::query()->create([
            'lead_id' => $lead->id,
            'user_id' => $userId,
            'type' => $data->type->value,
            'outcome' => $data->outcome,
            'notes' => $data->notes,
            'occurred_at' => $occurredAt,
        ]);

        if ($lead->last_contacted_at === null || $occurredAt->greaterThan($lead->last_contacted_at)) {
            $lead->last_contacted_at = $occurredAt;
            $lead->save();
        }

        return $activity;
    }

    public function findByExternalId(string $source, string $externalId): ?LeadData
    {
        $lead = Lead::query()
            ->with([
                'owner:id,uuid,first_name,last_name',
                'duplicateOf:id,uuid',
                'latestAppointment:id,lead_id,starts_at,ends_at,timezone,status',
            ])
            ->where('external_source', $source)
            ->where('external_id', $externalId)
            ->first();

        return $lead === null ? null : $this->toData($lead);
    }

    /**
     * @return list<DuplicateCandidate>
     */
    public function findDuplicateCandidates(string $email, ?string $phone, string $address, int $windowDays): array
    {
        $normalizedEmail = mb_strtolower(trim($email));
        $normalizedPhone = PhoneNumber::fromString($phone)?->value;

        return Lead::query()
            ->where('created_at', '>=', CarbonImmutable::now()->subDays($windowDays))
            ->where(function ($query) use ($normalizedEmail, $normalizedPhone, $address): void {
                if ($normalizedEmail !== '') {
                    $query->orWhere('email', $normalizedEmail);
                }

                if ($normalizedPhone !== null) {
                    $query->orWhere('phone', $normalizedPhone);
                }

                if ($address !== '') {
                    $query->orWhereLike('address', '%'.addcslashes($address, '\\%_').'%');
                }
            })
            ->limit(20)
            ->get(['uuid', 'email', 'phone', 'first_name', 'last_name', 'address', 'created_at'])
            ->map(static fn (Lead $lead): DuplicateCandidate => new DuplicateCandidate(
                uuid: $lead->uuid,
                email: $lead->email,
                phone: $lead->phone,
                firstName: $lead->first_name,
                lastName: $lead->last_name,
                address: $lead->address,
                createdAt: CarbonImmutable::parse($lead->created_at),
            ))
            ->values()
            ->all();
    }

    public function merge(string $targetUuid, string $duplicateUuid): LeadData
    {
        $target = DB::transaction(function () use ($targetUuid, $duplicateUuid): Lead {
            $lockOrder = [$targetUuid, $duplicateUuid];
            sort($lockOrder);
            $locked = [];

            foreach ($lockOrder as $uuid) {
                $locked[$uuid] = Lead::query()->where('uuid', $uuid)->lockForNoKeyUpdate()->firstOrFail();
            }

            $target = $locked[$targetUuid];
            $duplicate = $locked[$duplicateUuid];

            if (Appointment::query()->whereIn('lead_id', [$target->id, $duplicate->id])->exists()) {
                throw new LeadHasAppointmentsException;
            }

            LeadActivity::query()->where('lead_id', $duplicate->id)->update(['lead_id' => $target->id]);

            $duplicate->duplicate_of_id = $target->id;
            $duplicate->save();
            $duplicate->delete();

            $latestActivityAt = LeadActivity::query()->where('lead_id', $target->id)->max('occurred_at');
            $latest = $target->last_contacted_at;

            if ($duplicate->last_contacted_at !== null && ($latest === null || $duplicate->last_contacted_at->greaterThan($latest))) {
                $latest = $duplicate->last_contacted_at;
            }

            if ($latestActivityAt !== null) {
                $activityAt = CarbonImmutable::parse($latestActivityAt);

                if ($latest === null || $activityAt->greaterThan($latest)) {
                    $latest = $activityAt;
                }
            }

            if ($latest !== null) {
                $target->last_contacted_at = $latest;
                $target->save();
            }

            return $target;
        });

        return $this->find($target->uuid) ?? throw new \RuntimeException('Lead was not persisted.');
    }

    public function delete(string $uuid): void
    {
        DB::transaction(function () use ($uuid): void {
            $lead = Lead::query()->where('uuid', $uuid)->lockForNoKeyUpdate()->first();

            if ($lead === null) {
                return;
            }

            if (Appointment::query()->where('lead_id', $lead->id)->exists()) {
                throw new LeadHasAppointmentsException;
            }

            $lead->delete();
        });
    }

    public function restore(string $uuid): LeadData
    {
        try {
            $lead = DB::transaction(function () use ($uuid): Lead {
                $lead = Lead::onlyTrashed()->where('uuid', $uuid)->lockForUpdate()->firstOrFail();
                $lead->restore();

                return $lead;
            });
        } catch (UniqueConstraintViolationException) {
            throw LeadRestoreConflictException::forExternalId();
        }

        return $this->find($lead->uuid) ?? throw new \RuntimeException('Lead was not restored.');
    }

    /**
     * @param list<string> $uuids
     */
    public function bulkDelete(array $uuids): void
    {
        DB::transaction(function () use ($uuids): void {
            $leads = Lead::query()->whereIn('uuid', $uuids)->orderBy('id')->lockForNoKeyUpdate()->get();

            if ($leads->isEmpty()) {
                return;
            }

            if (Appointment::query()->whereIn('lead_id', $leads->modelKeys())->exists()) {
                throw new LeadHasAppointmentsException;
            }

            foreach ($leads as $lead) {
                $lead->delete();
            }
        });
    }

    /**
     * @param list<string> $uuids
     */
    public function bulkRestore(array $uuids): void
    {
        try {
            DB::transaction(function () use ($uuids): void {
                $leads = Lead::onlyTrashed()->whereIn('uuid', $uuids)->orderBy('id')->lockForUpdate()->get();

                foreach ($leads as $lead) {
                    $lead->restore();
                }
            });
        } catch (UniqueConstraintViolationException) {
            throw LeadRestoreConflictException::forBulk();
        }
    }

    public function count(LeadFilterData $filters): int
    {
        return Lead::query()->applyFilters($filters)->count();
    }

    /**
     * @return iterable<int, LeadListItemData>
     */
    public function stream(LeadFilterData $filters): iterable
    {
        foreach (Lead::query()
            ->applyFilters($filters)
            ->with(['owner:id,uuid,first_name,last_name'])
            ->select([
                'id', 'uuid', 'first_name', 'last_name', 'email', 'phone', 'city', 'state', 'region',
                'source', 'status', 'owner_id', 'is_emergency', 'is_spam',
                'last_contacted_at', 'created_at', 'deleted_at',
            ])
            ->lazy(500) as $lead) {
            yield $this->toListItem($lead);
        }
    }

    public function sourceReport(string $from, string $to): LeadSourceReportData
    {
        $timezone = Region::from(
            (string) config('lead-appointment.default_region', Region::Houston->value),
        )->timezone();

        $start = CarbonImmutable::parse($from, $timezone)->startOfDay()->utc();
        $end = CarbonImmutable::parse($to, $timezone)->endOfDay()->utc();

        $rows = DB::table('leads')
            ->selectRaw('source, COUNT(*) as leads, COUNT(DISTINCT CASE WHEN status = ? THEN id END) as won', [LeadStatus::Won->value])
            ->whereNull('deleted_at')
            ->whereBetween('created_at', [$start, $end])
            ->groupBy('source')
            ->orderByDesc('leads')
            ->get()
            ->map(static fn (object $row): array => (array) $row);

        $totals = [];
        $counts = [];
        $overallTotal = 0.0;
        $overallCount = 0;

        foreach (Lead::query()
            ->select(['source', 'created_at', 'last_contacted_at'])
            ->whereNotNull('last_contacted_at')
            ->whereBetween('created_at', [$start, $end])
            ->cursor() as $lead) {
            $source = $lead->source->value;
            $minutes = $lead->created_at->diffInMinutes($lead->last_contacted_at);

            $totals[$source] = ($totals[$source] ?? 0.0) + $minutes;
            $counts[$source] = ($counts[$source] ?? 0) + 1;
            $overallTotal += $minutes;
            $overallCount++;
        }

        $averages = [];

        foreach ($counts as $source => $count) {
            $averages[$source] = (int) round($totals[$source] / $count);
        }

        $average = $overallCount > 0 ? (int) round($overallTotal / $overallCount) : null;

        // One aggregate for every source instead of one COUNT per source row.
        // The join keeps the same scope as the per-source whereHas it replaces.
        $appointmentCounts = DB::table('appointments')
            ->join('leads', 'appointments.lead_id', '=', 'leads.id')
            ->whereNull('appointments.deleted_at')
            ->whereNull('leads.deleted_at')
            ->whereBetween('leads.created_at', [$start, $end])
            ->selectRaw('leads.source as source, COUNT(*) as aggregate')
            ->groupBy('leads.source')
            ->pluck('aggregate', 'source');

        $reportRows = $rows->map(function (array $row) use ($appointmentCounts, $averages): LeadSourceReportRowData {
            $source = (string) $row['source'];
            $appointments = (int) ($appointmentCounts[$source] ?? 0);
            $leads = (int) $row['leads'];
            $won = (int) $row['won'];

            return new LeadSourceReportRowData(
                source: $source,
                sourceLabel: LeadSource::tryFrom($source)?->label() ?? ucfirst($source),
                leads: $leads,
                appointments: $appointments,
                won: $won,
                conversionRate: $leads > 0 ? round($won / $leads, 4) : 0.0,
                averageFirstContactMinutes: $averages[$source] ?? null,
            );
        })->values()->all();

        return new LeadSourceReportData(
            from: $from,
            to: $to,
            rows: $reportRows,
            averageFirstContactMinutes: $average,
        );
    }

    private function toData(Lead $lead): LeadData
    {
        $address = collect([$lead->address, $lead->address_2, $lead->city, $lead->state, $lead->zip_code])
            ->filter(static fn (mixed $value): bool => $value !== null && $value !== '')
            ->implode(', ');

        $appointment = $lead->latestAppointment;
        $inspection = $appointment?->starts_at->setTimezone($appointment->timezone);
        $registeredAt = $lead->created_at?->setTimezone(
            $lead->region?->timezone()
            ?? Region::from((string) config('lead-appointment.default_region', Region::Houston->value))->timezone(),
        );

        $inspectionDate = $inspection?->toDateString() ?? $lead->inspection_date?->toDateString();
        $inspectionTime = $inspection?->format('H:i')
            ?? ($lead->inspection_time === null ? null : substr($lead->inspection_time, 0, 5));
        $inspectionStatus = $appointment === null
            ? $lead->inspection_status?->value
            : InspectionStatus::fromAppointment($appointment->status)->value;

        return new LeadData(
            uuid: $lead->uuid,
            firstName: $lead->first_name,
            lastName: $lead->last_name,
            fullName: trim($lead->first_name.' '.$lead->last_name),
            companyName: $lead->company_name,
            email: $lead->email,
            phone: $lead->phone,
            address: $lead->address,
            address2: $lead->address_2,
            city: $lead->city,
            state: $lead->state,
            zipCode: $lead->zip_code,
            addressLine: $address,
            region: $lead->region?->value,
            source: $lead->source->value,
            sourceDetail: $lead->source_detail,
            status: $lead->status->value,
            ownerUuid: $lead->owner?->uuid,
            ownerName: $lead->owner === null ? null : trim($lead->owner->first_name.' '.$lead->owner->last_name),
            duplicateOfUuid: $lead->duplicateOf?->uuid,
            isEmergency: $lead->is_emergency,
            isSpam: $lead->is_spam,
            spamScore: $lead->spam_score,
            spamReasons: $lead->spam_reasons ?? [],
            preferredLanguage: $lead->preferred_language,
            damageDescription: $lead->damage_description,
            insuranceCarrier: $lead->insurance_carrier,
            claimNumber: $lead->claim_number,
            smsConsent: $lead->sms_consent,
            smsConsentAt: $lead->sms_consent_at?->toIso8601String(),
            lastContactedAt: $lead->last_contacted_at?->toIso8601String(),
            convertedAt: $lead->converted_at?->toIso8601String(),
            notes: $lead->notes,
            attribution: $lead->attribution,
            createdAt: $lead->created_at?->toIso8601String(),
            updatedAt: $lead->updated_at?->toIso8601String(),
            deletedAt: $lead->deleted_at?->toIso8601String(),
            insuranceProperty: $lead->insurance_property,
            message: $lead->message,
            intentToClaim: $lead->intent_to_claim,
            followUpDate: $lead->follow_up_date?->toDateString(),
            additionalNote: $lead->additional_note,
            latitude: $lead->latitude,
            longitude: $lead->longitude,
            registrationDate: $registeredAt?->toDateString(),
            inspectionDate: $inspectionDate,
            inspectionTime: $inspectionTime,
            inspectionStatus: $inspectionStatus,
        );
    }

    private function toCardData(Lead $lead): LeadCardData
    {
        $appointment = $lead->latestAppointment;
        $inspectionAt = $appointment?->starts_at
            ?->setTimezone($appointment->timezone)
            ->toIso8601String();

        return new LeadCardData(
            uuid: $lead->uuid,
            fullName: trim($lead->first_name.' '.$lead->last_name),
            phone: $lead->phone,
            city: $lead->city,
            state: $lead->state,
            region: $lead->region?->value,
            source: $lead->source->value,
            status: $lead->status->value,
            isEmergency: $lead->is_emergency,
            isSpam: $lead->is_spam,
            lastContactedAt: $lead->last_contacted_at?->toIso8601String(),
            inspectionAt: $inspectionAt,
            createdAt: $lead->created_at?->toIso8601String(),
        );
    }

    private function toListItem(Lead $lead): LeadListItemData
    {
        return new LeadListItemData(
            uuid: $lead->uuid,
            firstName: $lead->first_name,
            lastName: $lead->last_name,
            fullName: trim($lead->first_name.' '.$lead->last_name),
            email: $lead->email,
            phone: $lead->phone,
            city: $lead->city,
            state: $lead->state,
            region: $lead->region?->value,
            source: $lead->source->value,
            status: $lead->status->value,
            ownerName: $lead->owner === null ? null : trim($lead->owner->first_name.' '.$lead->owner->last_name),
            isEmergency: $lead->is_emergency,
            isSpam: $lead->is_spam,
            lastContactedAt: $lead->last_contacted_at?->toIso8601String(),
            createdAt: $lead->created_at?->toIso8601String(),
            deletedAt: $lead->deleted_at?->toIso8601String(),
        );
    }

    private function toActivityData(LeadActivity $activity): LeadActivityData
    {
        $actor = $activity->user;

        return new LeadActivityData(
            uuid: $activity->uuid,
            type: $activity->type->value,
            outcome: $activity->outcome,
            notes: $activity->notes,
            occurredAt: $activity->occurred_at->toIso8601String(),
            actorName: $actor === null ? null : trim($actor->first_name.' '.$actor->last_name),
        );
    }
}
