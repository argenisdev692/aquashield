<?php

declare(strict_types=1);

namespace Modules\LeadAppointment\Infrastructure\Persistence\Eloquent\Models;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\LeadAppointment\Domain\Enums\LeadActivityType;
use Modules\LeadAppointment\Infrastructure\Persistence\Eloquent\Factories\LeadActivityFactory;

/**
 * @property int $id
 * @property string $uuid
 * @property int $lead_id
 * @property int|null $user_id
 * @property LeadActivityType $type
 * @property string|null $outcome
 * @property string|null $notes
 * @property CarbonImmutable $occurred_at
 *
 * @mixin \Eloquent
 */
#[Table('lead_activities')]
#[Fillable(['uuid', 'lead_id', 'user_id', 'type', 'outcome', 'notes', 'occurred_at'])]
final class LeadActivity extends Model
{
    /** @use HasFactory<LeadActivityFactory> */
    use HasFactory, HasUuids;

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
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => LeadActivityType::class,
            'occurred_at' => 'immutable_datetime',
        ];
    }

    protected static function newFactory(): LeadActivityFactory
    {
        return LeadActivityFactory::new();
    }
}
