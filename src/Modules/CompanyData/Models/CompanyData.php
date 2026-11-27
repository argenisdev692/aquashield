<?php

declare(strict_types=1);

namespace Modules\CompanyData\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\ActivityLog\Models\ActivityEntry;
use Shared\Domain\Ports\CompanyBrandingPort;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * @property int $user_id
 * @property string $uuid
 * @property ?string $signature_path
 * @property ?string $logo_path
 * @property ?string $logo_white_path
 * @property ?string $mark_path
 * @property int $id
 * @property bool $singleton
 * @property string|null $name
 * @property string $company_name
 * @property string|null $description
 * @property string|null $email
 * @property string|null $phone
 * @property string|null $nif_nipc
 * @property string|null $nie
 * @property string|null $bank_beneficiary
 * @property string|null $bank_iban
 * @property string|null $bank_bic
 * @property string|null $bank_name
 * @property string|null $invoice_notes
 * @property string|null $address
 * @property string|null $address_2
 * @property string|null $zip_code
 * @property string|null $city
 * @property string|null $state
 * @property string|null $country
 * @property string|null $country_code
 * @property float|null $latitude
 * @property float|null $longitude
 * @property string|null $website
 * @property string|null $facebook_link
 * @property string|null $instagram_link
 * @property string|null $linkedin_link
 * @property string|null $twitter_link
 * @property string|null $tiktok_link
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, ActivityEntry> $activitiesAsSubject
 * @property-read int|null $activities_as_subject_count
 * @property-read User|null $user
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CompanyData newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CompanyData newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CompanyData query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CompanyData whereAddress($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CompanyData whereAddress2($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CompanyData whereBankBeneficiary($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CompanyData whereBankBic($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CompanyData whereBankIban($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CompanyData whereBankName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CompanyData whereCity($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CompanyData whereCompanyName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CompanyData whereCountry($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CompanyData whereCountryCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CompanyData whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CompanyData whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CompanyData whereEmail($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CompanyData whereFacebookLink($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CompanyData whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CompanyData whereInstagramLink($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CompanyData whereInvoiceNotes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CompanyData whereLatitude($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CompanyData whereLinkedinLink($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CompanyData whereLogoPath($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CompanyData whereLogoWhitePath($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CompanyData whereLongitude($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CompanyData whereMarkPath($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CompanyData whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CompanyData whereNie($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CompanyData whereNifNipc($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CompanyData wherePhone($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CompanyData whereSignaturePath($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CompanyData whereSingleton($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CompanyData whereState($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CompanyData whereTiktokLink($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CompanyData whereTwitterLink($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CompanyData whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CompanyData whereUserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CompanyData whereUuid($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CompanyData whereWebsite($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CompanyData whereZipCode($value)
 *
 * @mixin \Eloquent
 */
#[Fillable([
    'name',
    'company_name',
    'description',
    'email',
    'phone',
    'nif_nipc',
    'nie',
    'bank_beneficiary',
    'bank_iban',
    'bank_bic',
    'bank_name',
    'invoice_notes',
    'address',
    'address_2',
    'zip_code',
    'city',
    'state',
    'country',
    'country_code',
    'latitude',
    'longitude',
    'website',
    'facebook_link',
    'instagram_link',
    'linkedin_link',
    'twitter_link',
    'tiktok_link',
])]
class CompanyData extends Model
{
    use HasUuids, LogsActivity;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'company_data';

    /**
     * The singleton profile: this module keeps exactly one company row, so the
     * oldest one is THE row. One definition of that rule, no repository layer.
     */
    public static function current(): ?self
    {
        return self::query()->oldest('id')->first();
    }

    /**
     * The attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'latitude' => 'float',
            'longitude' => 'float',
            // Financial/tax fields are encrypted at rest. CompanyProfile's PDF
            // read decrypts them through the same crypt contract; the model's
            // normal reads/writes use Laravel's `encrypted` cast.
            'nif_nipc' => 'encrypted',
            'nie' => 'encrypted',
            'bank_beneficiary' => 'encrypted',
            'bank_iban' => 'encrypted',
            'bank_bic' => 'encrypted',
            'bank_name' => 'encrypted',
            'invoice_notes' => 'encrypted',
        ];
    }

    /**
     * Get the columns that should receive a unique identifier.
     *
     * @return array<int, string>
     */
    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    /**
     * Branding is cached behind CompanyBrandingPort. Flush it whenever the
     * singleton changes so email/PDF/hero renders never serve a stale profile.
     */
    protected static function booted(): void
    {
        static::saved(static function (): void {
            DB::afterCommit(static fn () => app(CompanyBrandingPort::class)->forget());
        });

        static::deleted(static function (): void {
            DB::afterCommit(static fn () => app(CompanyBrandingPort::class)->forget());
        });
    }

    /**
     * Audit only non-PII, non-financial profile fields. Bank details, tax ids,
     * the signature path, the contact person and email/phone are deliberately
     * excluded (OWASP §9 allowlist).
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'company_name',
                'address',
                'address_2',
                'zip_code',
                'city',
                'state',
                'country',
                'country_code',
                'website',
                'facebook_link',
                'instagram_link',
                'linkedin_link',
                'twitter_link',
                'tiktok_link',
                // Brand-asset keys are non-sensitive and mutate the public
                // site; the private signature path stays out of the trail.
                'logo_path',
                'logo_white_path',
                'mark_path',
            ])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    /**
     * The user that owns this company data record.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
