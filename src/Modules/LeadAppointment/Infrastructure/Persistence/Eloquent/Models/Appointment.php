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
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\LeadAppointment\Application\Dtos\AppointmentFilterData;
use Modules\LeadAppointment\Domain\Enums\AppointmentKind;
use Modules\LeadAppointment\Domain\Enums\AppointmentStatus;
use Modules\LeadAppointment\Domain\Enums\Region;
use Modules\LeadAppointment\Infrastructure\Persistence\Eloquent\Factories\AppointmentFactory;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * @property int $id
 * @property string $uuid
 * @property int $lead_id
 * @property AppointmentKind $kind
 * @property AppointmentStatus $status
 * @property CarbonImmutable $starts_at
 * @property CarbonImmutable $ends_at
 * @property string $timezone
 * @property CarbonImmutable|null $previous_starts_at
 * @property CarbonImmutable|null $previous_ends_at
 * @property Region $region
 * @property string|null $google_event_id
 * @property string|null $cancel_reason
 * @property CarbonImmutable|null $cancelled_at
 * @property CarbonImmutable|null $completed_at
 * @property int|null $created_by
 * @property string|null $notes
 * @property CarbonImmutable|null $deleted_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 *
 * @mixin \Eloquent
 */
#[Table('appointments')]
#[Fillable([
    'uuid', 'lead_id', 'kind', 'status', 'starts_at', 'ends_at',
    'timezone', 'previous_starts_at', 'previous_ends_at', 'region', 'google_event_id',
    'cancel_reason', 'cancelled_at', 'completed_at', 'created_by', 'notes',
])]
final class Appointment extends Model
{
    /** @use HasFactory<AppointmentFactory> */
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
     * @return BelongsTo<Lead, $this>
     */
    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @param Builder<self> $query
     * @return Builder<self>
     */
    public function scopeApplyFilters(Builder $query, AppointmentFilterData $filters): Builder
    {
        return $query
            ->when($filters->search !== null && $filters->search !== '', function (Builder $q) use ($filters): void {
                $term = '%'.addcslashes($filters->search ?? '', '\\%_').'%';
                $q->where(function (Builder $w) use ($term): void {
                    $w->where('notes', 'like', $term)
                        ->orWhereHas('lead', function (Builder $lead) use ($term): void {
                            $lead->where('first_name', 'like', $term)
                                ->orWhere('last_name', 'like', $term)
                                ->orWhere('email', 'like', $term)
                                ->orWhere('phone', 'like', $term);
                        });
                });
            })
            ->when($filters->appointmentStatus !== null, fn (Builder $q): Builder => $q->where('status', $filters->appointmentStatus))
            ->when($filters->kind !== null, fn (Builder $q): Builder => $q->where('kind', $filters->kind))
            ->when($filters->region !== null, fn (Builder $q): Builder => $q->where('region', $filters->region))
            ->when($filters->recordState === 'trashed', fn (Builder $q): Builder => $q->onlyTrashed())
            ->when($filters->recordState === 'all', fn (Builder $q): Builder => $q->withTrashed())
            ->when($filters->dateFrom !== null, fn (Builder $q): Builder => $q->whereDate('starts_at', '>=', $filters->dateFrom))
            ->when($filters->dateTo !== null, fn (Builder $q): Builder => $q->whereDate('starts_at', '<=', $filters->dateTo))
            ->orderBy($filters->sortField, $filters->sortOrder === -1 ? 'desc' : 'asc')
            ->orderBy('id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => AppointmentKind::class,
            'status' => AppointmentStatus::class,
            'region' => Region::class,
            'starts_at' => 'immutable_datetime',
            'ends_at' => 'immutable_datetime',
            'previous_starts_at' => 'immutable_datetime',
            'previous_ends_at' => 'immutable_datetime',
            'cancelled_at' => 'immutable_datetime',
            'completed_at' => 'immutable_datetime',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['status', 'starts_at', 'ends_at', 'google_event_id', 'cancel_reason'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName('appointment');
    }

    protected static function newFactory(): AppointmentFactory
    {
        return AppointmentFactory::new();
    }
}
