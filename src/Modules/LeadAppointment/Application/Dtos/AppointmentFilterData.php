<?php

declare(strict_types=1);

namespace Modules\LeadAppointment\Application\Dtos;

use Modules\LeadAppointment\Domain\Enums\AppointmentKind;
use Modules\LeadAppointment\Domain\Enums\AppointmentStatus;
use Modules\LeadAppointment\Domain\Enums\Region;
use Shared\Application\DTOs\SoftDeleteFilterData;
use Spatie\LaravelData\Attributes\MapName;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

#[MapName(SnakeCaseMapper::class)]
final class AppointmentFilterData extends SoftDeleteFilterData
{
    /** @var list<string> */
    public const array ALLOWED_FILTERS = [
        'status',
        'record_state',
        'kind',
        'region',
        'date_from',
        'date_to',
        'scheduled_only',
    ];

    /** @var list<string> */
    public const array SORTABLE = ['starts_at', 'created_at', 'status', 'kind'];

    public function __construct(
        ?string $search = null,
        ?string $dateFrom = null,
        ?string $dateTo = null,
        public readonly ?string $appointmentStatus = null,
        public readonly ?string $recordState = null,
        public readonly ?string $kind = null,
        public readonly ?string $region = null,
        public readonly ?string $scheduledOnly = null,
        public readonly string $sortField = 'starts_at',
        public readonly int $sortOrder = 1,
        public readonly int $page = 1,
        public readonly int $perPage = 15,
    ) {
        parent::__construct($search, null, $dateFrom, $dateTo);
    }

    /**
     * @return array<string, array<int, string>>
     */
    public static function rules(): array
    {
        return [
            ...self::baseRules(),
            'appointment_status' => ['nullable', 'string', 'in:'.implode(',', array_column(AppointmentStatus::cases(), 'value'))],
            'record_state' => ['nullable', 'string', 'in:active,trashed,all'],
            'kind' => ['nullable', 'string', 'in:'.implode(',', array_column(AppointmentKind::cases(), 'value'))],
            'region' => ['nullable', 'string', 'in:'.implode(',', array_column(Region::cases(), 'value'))],
            'scheduled_only' => ['nullable', 'string', 'in:0,1'],
            'sort_field' => ['required', 'string', 'in:'.implode(',', self::SORTABLE)],
            'sort_order' => ['required', 'integer', 'in:-1,1'],
            'page' => ['required', 'integer', 'min:1', 'max:10000'],
            'per_page' => ['required', 'integer', 'min:1', 'max:100'],
        ];
    }
}
