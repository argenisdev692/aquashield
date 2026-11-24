<?php

declare(strict_types=1);

namespace Modules\LeadAppointment\Infrastructure\Persistence\Eloquent\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $uuid
 * @property string $provider
 * @property string $external_id
 * @property array<string, mixed> $payload
 * @property string $status
 * @property int $attempts
 * @property string|null $last_error
 * @property CarbonImmutable $received_at
 * @property CarbonImmutable|null $processed_at
 *
 * @mixin \Eloquent
 */
#[Table('lead_ingestion_events')]
#[Fillable(['uuid', 'provider', 'external_id', 'payload', 'status', 'attempts', 'last_error', 'received_at', 'processed_at'])]
final class LeadIngestionEvent extends Model
{
    use HasUuids;

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
            'payload' => 'array',
            'attempts' => 'integer',
            'received_at' => 'immutable_datetime',
            'processed_at' => 'immutable_datetime',
        ];
    }
}
