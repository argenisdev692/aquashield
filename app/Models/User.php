<?php

declare(strict_types=1);

namespace App\Models;

use App\Notifications\ResetPasswordNotification;
use Database\Factories\UserFactory;
use Illuminate\Auth\MustVerifyEmail as MustVerifyEmailTrait;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Notifications\DatabaseNotificationCollection;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Passkeys\Contracts\PasskeyUser;
use Laravel\Passkeys\Passkey;
use Laravel\Passkeys\PasskeyAuthenticatable;
use Laravel\Sanctum\HasApiTokens;
use Laravel\Sanctum\PersonalAccessToken;
use Modules\CompanyData\Models\CompanyData;
use Modules\Users\Enums\UserStatus;
use Spatie\OneTimePasswords\Models\Concerns\HasOneTimePasswords;
use Spatie\OneTimePasswords\Models\OneTimePassword;
use Spatie\Permission\Traits\HasRoles;

/**
 * @property int $id
 * @property string $uuid
 * @property string $first_name
 * @property string|null $last_name
 * @property string $name
 * @property string|null $username
 * @property string $email
 * @property Carbon|null $email_verified_at
 * @property UserStatus $status
 * @property Carbon|null $invitation_sent_at
 * @property Carbon|null $activated_at
 * @property Carbon|null $suspended_at
 * @property bool $must_change_password
 * @property Carbon|null $passkey_prompt_dismissed_at
 * @property string|null $password
 * @property int $failed_login_attempts
 * @property Carbon|null $locked_until
 * @property string|null $google_id
 * @property string|null $phone
 * @property string|null $date_of_birth
 * @property string|null $address
 * @property string|null $address_2
 * @property string|null $zip_code
 * @property string|null $city
 * @property string|null $state
 * @property string|null $country
 * @property string|null $gender
 * @property string|null $profile_photo_path
 * @property float|null $latitude
 * @property float|null $longitude
 * @property bool $terms_and_conditions
 * @property int|null $registered_by_id
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property string|null $two_factor_confirmed_at
 * @property-read DatabaseNotificationCollection<int, DatabaseNotification> $notifications
 * @property-read int|null $notifications_count
 *
 * @method static \Database\Factories\UserFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereEmail($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereEmailVerifiedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereGoogleId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User wherePassword($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereRememberToken($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereTwoFactorConfirmedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereTwoFactorRecoveryCodes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereTwoFactorSecret($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereUpdatedAt($value)
 *
 * @property-read Collection<int, Permission> $permissions
 * @property-read int|null $permissions_count
 * @property-read Collection<int, Role> $roles
 * @property-read int|null $roles_count
 * @property-read Collection<int, Permission> $teams
 * @property-read int|null $teams_count
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User permission($permissions, bool $without = false)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User role($roles, ?string $guard = null, bool $without = false)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User team($teams, bool $without = false)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User withoutPermission($permissions)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User withoutRole($roles, ?string $guard = null)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User withoutTeam($teams)
 *
 * @property-read int|null $media_count
 * @property-read CompanyData|null $companyData
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereUuid($value)
 *
 * @property-read Collection<int, OneTimePassword> $oneTimePasswords
 * @property-read int|null $one_time_passwords_count
 * @property-read Collection<int, PersonalAccessToken> $tokens
 * @property-read int|null $tokens_count
 * @property-read User|null $registeredBy
 * @property-read Collection<int, User> $registeredUsers
 * @property-read int|null $registered_users_count
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereAddress($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereAddress2($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereCity($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereCountry($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereDateOfBirth($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereFirstName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereGender($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereLastName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereLatitude($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereLongitude($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User wherePhone($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereProfilePhotoPath($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereRegisteredById($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereState($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereTermsAndConditions($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereUsername($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereZipCode($value)
 *
 * @mixin Builder
 *
 * @method static Builder<static>|User onlyTrashed()
 * @method static Builder<static>|User whereFailedLoginAttempts($value)
 * @method static Builder<static>|User whereLockedUntil($value)
 * @method static Builder<static>|User withTrashed(bool $withTrashed = true)
 * @method static Builder<static>|User withoutTrashed()
 * @method static Builder<static>|User whereDeletedAt($value)
 *
 * @property-read Collection<int, Passkey> $passkeys
 * @property-read int|null $passkeys_count
 *
 * @method static Builder<static>|User wherePasskeyPromptDismissedAt($value)
 *
 * @property-read Collection<int, SavedView> $savedViews
 * @property-read int|null $saved_views_count
 *
 * @method static Builder<static>|User whereActivatedAt($value)
 * @method static Builder<static>|User whereInvitationSentAt($value)
 * @method static Builder<static>|User whereMustChangePassword($value)
 * @method static Builder<static>|User whereStatus($value)
 * @method static Builder<static>|User whereSuspendedAt($value)
 *
 * @mixin \Eloquent
 */
#[Fillable([
    'first_name',
    'last_name',
    'username',
    'email',
    'password',
    'status',
    'invitation_sent_at',
    'activated_at',
    'suspended_at',
    'must_change_password',
    'phone',
    'date_of_birth',
    'address',
    'address_2',
    'zip_code',
    'city',
    'state',
    'country',
    'gender',
    'profile_photo_path',
    'latitude',
    'longitude',
    'terms_and_conditions',
    'google_id',
    'uuid',
    'registered_by_id',
    'passkey_prompt_dismissed_at',
])]
#[Hidden(['password', 'remember_token', 'google_id'])]
class User extends Authenticatable implements MustVerifyEmail, PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasOneTimePasswords, HasRoles, HasUuids, MustVerifyEmailTrait, Notifiable, PasskeyAuthenticatable, SoftDeletes, TwoFactorAuthenticatable;

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
     * Send the branded password-set link.
     *
     * The signature intentionally stays untyped: the framework's
     * CanResetPassword contract declares it without a parameter type.
     *
     * @param string $token
     */
    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new ResetPasswordNotification((string) $token));
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'passkey_prompt_dismissed_at' => 'datetime',
            'status' => UserStatus::class,
            'invitation_sent_at' => 'datetime',
            'activated_at' => 'datetime',
            'suspended_at' => 'datetime',
            'must_change_password' => 'boolean',
            'password' => 'hashed',
            'failed_login_attempts' => 'integer',
            'locked_until' => 'datetime',
            'latitude' => 'float',
            'longitude' => 'float',
            'terms_and_conditions' => 'boolean',
        ];
    }

    /**
     * Staff lifecycle predicates.
     *
     * They live on the aggregate because every transport (Fortify, passkeys,
     * OTP, API tokens and the admin guard) asks the same question. The role
     * hierarchy is deliberately NOT here — that belongs to AccessCeiling.
     */
    public function isActive(): bool
    {
        return $this->status === UserStatus::Active;
    }

    public function isInvited(): bool
    {
        return $this->status === UserStatus::Invited;
    }

    public function isSuspended(): bool
    {
        return $this->status === UserStatus::Suspended;
    }

    public function requiresPasswordChange(): bool
    {
        return $this->must_change_password;
    }

    /**
     * The full name is a compatibility view over the canonical first/last pair.
     *
     * Existing email templates, the audit trail and the shell header all read
     * `name`; keeping it as an accessor means those call sites do not need a
     * second name field in the database. The setter is what lets the existing
     * UserSeeder continue to seed `name` without a schema column.
     */
    protected function name(): Attribute
    {
        return Attribute::make(
            get: static function (mixed $value, array $attributes): string {
                return trim(implode(' ', array_filter([
                    (string) ($attributes['first_name'] ?? ''),
                    (string) ($attributes['last_name'] ?? ''),
                ])));
            },
            set: static function (mixed $value): array {
                $parts = preg_split('/\s+/', trim((string) $value)) ?: [];
                $first = array_shift($parts) ?? '';

                return [
                    'first_name' => $first,
                    'last_name' => $parts === [] ? null : implode(' ', $parts),
                ];
            },
        );
    }

    /**
     * The singleton company profile owned by this user.
     *
     * @return HasOne<CompanyData, $this>
     */
    public function companyData(): HasOne
    {
        return $this->hasOne(CompanyData::class);
    }

    /**
     * Named table views owned by this user.
     *
     * @return HasMany<SavedView, $this>
     */
    public function savedViews(): HasMany
    {
        return $this->hasMany(SavedView::class);
    }

    /**
     * The authenticated user who created this account, when there was one.
     *
     * Seeded/bootstrap accounts and public self-registrations have no creator;
     * the column is nullable for exactly those two cases.
     *
     * @return BelongsTo<User, $this>
     */
    public function registeredBy(): BelongsTo
    {
        return $this->belongsTo(self::class, 'registered_by_id');
    }

    /**
     * @return HasMany<User, $this>
     */
    public function registeredUsers(): HasMany
    {
        return $this->hasMany(self::class, 'registered_by_id');
    }
}
