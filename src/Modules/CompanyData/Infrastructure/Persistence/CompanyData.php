<?php

declare(strict_types=1);

namespace Modules\CompanyData\Infrastructure\Persistence;

use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Shared\Infrastructure\Company\CompanyProfile;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * @property int $user_id
 * @property string $uuid
 * @property ?string $signature_path
 * @property ?string $logo_path
 * @property ?string $logo_white_path
 * @property ?string $mark_path
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
    use HasUuids, LogsActivity, SoftDeletes;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'company_data';

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
     * CompanyProfile caches the branding payload. Flush it whenever the
     * singleton changes so email/PDF/hero renders never serve a stale profile.
     */
    protected static function booted(): void
    {
        static::saved(static function (): void {
            CompanyProfile::forget();
        });

        static::deleted(static function (): void {
            CompanyProfile::forget();
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
