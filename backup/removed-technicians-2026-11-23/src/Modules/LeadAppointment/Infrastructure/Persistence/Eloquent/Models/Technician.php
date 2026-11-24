<?php

declare(strict_types=1);

namespace Modules\LeadAppointment\Infrastructure\Persistence\Eloquent\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\LeadAppointment\Domain\Enums\Region;
use Modules\LeadAppointment\Infrastructure\Persistence\Eloquent\Factories\TechnicianFactory;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * A field technician may exist without a login: the crew lead is not
 * necessarily an application user. `user_id` is nullable on purpose.
 *
 * @property int $id
 * @property string $uuid
 * @property int|null $user_id
 * @property string $name
 * @property Region $region
 * @property string|null $phone
 * @property string|null $email
 * @property bool $is_active
 * @property int $daily_capacity
 * @property int $travel_buffer_minutes
 *
 * @mixin \Eloquent
 */
#[Table('technicians')]
#[Fillable(['uuid', 'user_id', 'name', 'region', 'phone', 'email', 'is_active', 'daily_capacity', 'travel_buffer_minutes'])]
final class Technician extends Model
{
    /** @use HasFactory<TechnicianFactory> */
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
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<AvailabilityRule, $this>
     */
    public function availabilityRules(): HasMany
    {
        return $this->hasMany(AvailabilityRule::class);
    }

    /**
     * @return HasMany<AvailabilityException, $this>
     */
    public function availabilityExceptions(): HasMany
    {
        return $this->hasMany(AvailabilityException::class);
    }

    /**
     * @return HasMany<Appointment, $this>
     */
    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'region' => Region::class,
            'is_active' => 'boolean',
            'daily_capacity' => 'integer',
            'travel_buffer_minutes' => 'integer',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'region', 'is_active', 'daily_capacity', 'travel_buffer_minutes'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName('technician');
    }

    protected static function newFactory(): TechnicianFactory
    {
        return TechnicianFactory::new();
    }
}
