<?php

declare(strict_types=1);

namespace Modules\LeadAppointment\Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\LeadAppointment\Infrastructure\Persistence\Eloquent\Factories\AvailabilityRuleFactory;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Company-wide weekly template. Multiple rows per weekday are allowed
 * (split shift). Technician-scoped rules return with Claim + Scope Sheet.
 *
 * @property int $id
 * @property string $uuid
 * @property int $day_of_week
 * @property string $start_time
 * @property string $end_time
 * @property bool $is_available
 *
 * @mixin \Eloquent
 */
#[Table('availability_rules')]
#[Fillable(['uuid', 'day_of_week', 'start_time', 'end_time', 'is_available'])]
final class AvailabilityRule extends Model
{
    /** @use HasFactory<AvailabilityRuleFactory> */
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
            'day_of_week' => 'integer',
            'is_available' => 'boolean',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['day_of_week', 'start_time', 'end_time', 'is_available'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName('availability_rule');
    }

    protected static function newFactory(): AvailabilityRuleFactory
    {
        return AvailabilityRuleFactory::new();
    }
}
