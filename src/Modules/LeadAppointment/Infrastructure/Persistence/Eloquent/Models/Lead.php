<?php

declare(strict_types=1);

namespace Modules\LeadAppointment\Infrastructure\Persistence\Eloquent\Models;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\LeadAppointment\Application\Dtos\LeadFilterData;
use Modules\LeadAppointment\Domain\Enums\AppointmentStatus;
use Modules\LeadAppointment\Domain\Enums\InspectionStatus;
use Modules\LeadAppointment\Domain\Enums\LeadSource;
use Modules\LeadAppointment\Domain\Enums\LeadStatus;
use Modules\LeadAppointment\Domain\Enums\Region;
use Modules\LeadAppointment\Infrastructure\Persistence\Eloquent\Factories\LeadFactory;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * @property int $id
 * @property string $uuid
 * @property string $first_name
 * @property string $last_name
 * @property string|null $company_name
 * @property string|null $email
 * @property string|null $phone
 * @property string|null $address
 * @property string|null $address_2
 * @property string|null $city
 * @property string|null $state
 * @property string|null $zip_code
 * @property string $country
 * @property bool $insurance_property
 * @property Region|null $region
 * @property LeadSource $source
 * @property string|null $source_detail
 * @property string|null $external_source
 * @property string|null $external_id
 * @property LeadStatus $status
 * @property int|null $owner_id
 * @property int|null $created_by
 * @property int|null $duplicate_of_id
 * @property bool $is_emergency
 * @property string $preferred_language
 * @property string|null $damage_description
 * @property string|null $message
 * @property bool|null $intent_to_claim
 * @property CarbonImmutable|null $follow_up_date
 * @property CarbonImmutable|null $inspection_date
 * @property string|null $inspection_time
 * @property InspectionStatus|null $inspection_status
 * @property string|null $additional_note
 * @property float|null $latitude
 * @property float|null $longitude
 * @property string|null $insurance_carrier
 * @property string|null $claim_number
 * @property array<string, mixed>|null $attribution
 * @property bool $is_spam
 * @property int $spam_score
 * @property array<int, string>|null $spam_reasons
 * @property bool $sms_consent
 * @property CarbonImmutable|null $sms_consent_at
 * @property string|null $sms_consent_ip
 * @property string|null $sms_consent_user_agent
 * @property string|null $sms_consent_source
 * @property string|null $consent_text_version
 * @property CarbonImmutable|null $opted_out_at
 * @property CarbonImmutable|null $last_contacted_at
 * @property CarbonImmutable|null $converted_at
 * @property string|null $notes
 * @property CarbonImmutable|null $deleted_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 *
 * @mixin \Eloquent
 */
#[Table('leads')]
#[Fillable([
    'first_name', 'last_name', 'company_name', 'email', 'phone', 'address', 'address_2',
    'city', 'state', 'zip_code', 'country', 'insurance_property', 'region', 'source',
    'source_detail', 'external_source', 'external_id', 'status', 'owner_id', 'created_by',
    'duplicate_of_id',
    'is_emergency', 'preferred_language', 'damage_description', 'message', 'intent_to_claim',
    'follow_up_date', 'inspection_date', 'inspection_time', 'inspection_status', 'additional_note',
    'latitude', 'longitude', 'insurance_carrier', 'claim_number',
    'attribution', 'is_spam', 'spam_score', 'spam_reasons', 'sms_consent', 'sms_consent_at',
    'sms_consent_ip', 'sms_consent_user_agent', 'sms_consent_source', 'consent_text_version',
    'opted_out_at', 'last_contacted_at', 'converted_at', 'notes',
])]
final class Lead extends Model
{
    /** @use HasFactory<LeadFactory> */
    use HasFactory, HasUuids, LogsActivity, SoftDeletes;

    /** @var list<string> */
    protected $hidden = ['id'];

    /**
     * @return list<string>
     */
    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * The authenticated user who captured the lead. Null for public website
     * and Meta intake, which have no session actor.
     *
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return BelongsTo<self, $this>
     */
    public function duplicateOf(): BelongsTo
    {
        return $this->belongsTo(self::class, 'duplicate_of_id');
    }

    /**
     * @return HasMany<self, $this>
     */
    public function duplicates(): HasMany
    {
        return $this->hasMany(self::class, 'duplicate_of_id');
    }

    /**
     * @return HasMany<LeadActivity, $this>
     */
    public function activities(): HasMany
    {
        return $this->hasMany(LeadActivity::class);
    }

    /**
     * @return HasMany<Appointment, $this>
     */
    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }

    /**
     * Latest non-cancelled visit. The legacy lead table showed inspection
     * date/time/status inline; in the normalized schema those columns live on
     * `appointments`, so the read model derives them from this relation.
     *
     * @return HasOne<Appointment, $this>
     */
    public function latestAppointment(): HasOne
    {
        return $this->hasOne(Appointment::class)->ofMany(
            ['starts_at' => 'max'],
            fn (Builder $query): Builder => $query->whereNotIn('status', [
                AppointmentStatus::Cancelled->value,
                AppointmentStatus::NoShow->value,
            ]),
        );
    }

    /**
     * @param Builder<self> $query
     * @return Builder<self>
     */
    public function scopeApplyFilters(Builder $query, LeadFilterData $filters): Builder
    {
        $sortField = in_array($filters->sortField, LeadFilterData::SORTABLE, true)
            ? $filters->sortField
            : 'created_at';

        return $query
            ->when($filters->search !== null && $filters->search !== '', function (Builder $q) use ($filters): void {
                $term = '%'.addcslashes($filters->search ?? '', '\\%_').'%';
                $q->where(function (Builder $w) use ($term): void {
                    $w->whereLike('first_name', $term)
                        ->orWhereLike('last_name', $term)
                        ->orWhereLike('company_name', $term)
                        ->orWhereLike('email', $term)
                        ->orWhereLike('phone', $term)
                        ->orWhereLike('address', $term)
                        ->orWhereLike('city', $term);
                });
            })
            ->when($filters->pipelineStatus !== null, fn (Builder $q): Builder => $q->where('status', $filters->pipelineStatus))
            ->when($filters->source !== null, fn (Builder $q): Builder => $q->where('source', $filters->source))
            ->when($filters->region !== null, fn (Builder $q): Builder => $q->where('region', $filters->region))
            ->when(
                $filters->ownerUuid !== null,
                fn (Builder $q): Builder => $q->whereHas('owner', fn (Builder $owner): Builder => $owner->where('uuid', $filters->ownerUuid)),
            )
            ->when($filters->emergency === 'yes', fn (Builder $q): Builder => $q->where('is_emergency', true))
            ->when($filters->emergency === 'no', fn (Builder $q): Builder => $q->where('is_emergency', false))
            ->when($filters->spam === 'spam', fn (Builder $q): Builder => $q->where('is_spam', true))
            ->when($filters->spam === 'ham', fn (Builder $q): Builder => $q->where('is_spam', false))
            ->when($filters->recordState === 'trashed', fn (Builder $q): Builder => $q->onlyTrashed())
            ->when($filters->recordState === 'all', fn (Builder $q): Builder => $q->withTrashed())
            ->when($filters->dateFrom !== null || $filters->dateTo !== null, function (Builder $q) use ($filters): void {
                $timezone = Region::from(
                    (string) config('lead-appointment.default_region', Region::Houston->value),
                )->timezone();

                if ($filters->dateFrom !== null) {
                    $q->where('created_at', '>=', CarbonImmutable::parse($filters->dateFrom, $timezone)->startOfDay()->utc());
                }

                if ($filters->dateTo !== null) {
                    $q->where('created_at', '<=', CarbonImmutable::parse($filters->dateTo, $timezone)->endOfDay()->utc());
                }
            })
            ->when($filters->convertedFrom !== null || $filters->convertedTo !== null, function (Builder $q) use ($filters): void {
                $timezone = Region::from(
                    (string) config('lead-appointment.default_region', Region::Houston->value),
                )->timezone();

                if ($filters->convertedFrom !== null) {
                    $q->where('converted_at', '>=', CarbonImmutable::parse($filters->convertedFrom, $timezone)->startOfDay()->utc());
                }

                if ($filters->convertedTo !== null) {
                    $q->where('converted_at', '<=', CarbonImmutable::parse($filters->convertedTo, $timezone)->endOfDay()->utc());
                }
            })
            ->orderBy($sortField, $filters->sortOrder === -1 ? 'desc' : 'asc')
            ->orderBy('id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'region' => Region::class,
            'source' => LeadSource::class,
            'status' => LeadStatus::class,
            'insurance_property' => 'boolean',
            'intent_to_claim' => 'boolean',
            'follow_up_date' => 'immutable_date',
            'inspection_date' => 'immutable_date',
            'inspection_status' => InspectionStatus::class,
            'latitude' => 'float',
            'longitude' => 'float',
            'is_emergency' => 'boolean',
            'is_spam' => 'boolean',
            'spam_score' => 'integer',
            'spam_reasons' => 'array',
            'attribution' => 'array',
            'sms_consent' => 'boolean',
            'sms_consent_at' => 'immutable_datetime',
            'opted_out_at' => 'immutable_datetime',
            'last_contacted_at' => 'immutable_datetime',
            'converted_at' => 'immutable_datetime',
        ];
    }

    /**
     * Audit only the operational fields; guest-submitted PII is deliberately
     * NOT logged.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'status', 'owner_id', 'is_emergency', 'is_spam', 'last_contacted_at', 'converted_at',
                'duplicate_of_id', 'inspection_date', 'inspection_time', 'inspection_status',
            ])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName('lead');
    }

    protected static function newFactory(): LeadFactory
    {
        return LeadFactory::new();
    }
}
