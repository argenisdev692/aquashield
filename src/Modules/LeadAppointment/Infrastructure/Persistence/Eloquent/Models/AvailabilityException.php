<?php

declare(strict_types=1);

namespace Modules\LeadAppointment\Infrastructure\Persistence\Eloquent\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\LeadAppointment\Domain\Enums\AvailabilitySource;
use Modules\LeadAppointment\Infrastructure\Persistence\Eloquent\Factories\AvailabilityExceptionFactory;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * @property int $id
 * @property string $uuid
 * @property CarbonImmutable $date
 * @property bool $is_available
 * @property string|null $start_time
 * @property string|null $end_time
 * @property string|null $reason
 * @property AvailabilitySource $source
 * @property CarbonImmutable|null $deleted_at
 *
 * @mixin \Eloquent
 */
#[Table('availability_exceptions')]
#[Fillable(['date', 'is_available', 'start_time', 'end_time', 'reason', 'source'])]
final class AvailabilityException extends Model
{
    /** @use HasFactory<AvailabilityExceptionFactory> */
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
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date' => 'immutable_date',
            'is_available' => 'boolean',
            'source' => AvailabilitySource::class,
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['date', 'is_available', 'start_time', 'end_time', 'reason', 'source'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName('availability_exception');
    }

    protected static function newFactory(): AvailabilityExceptionFactory
    {
        return AvailabilityExceptionFactory::new();
    }
}
