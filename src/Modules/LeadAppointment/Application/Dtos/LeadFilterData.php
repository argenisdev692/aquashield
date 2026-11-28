<?php

declare(strict_types=1);

namespace Modules\LeadAppointment\Application\Dtos;

use Modules\LeadAppointment\Domain\Enums\LeadSource;
use Modules\LeadAppointment\Domain\Enums\LeadStatus;
use Modules\LeadAppointment\Domain\Enums\Region;
use Shared\Application\DTOs\SoftDeleteFilterData;
use Spatie\LaravelData\Attributes\MapName;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

/**
 * The one lead list/export filter contract. The admin table and the export
 * controller both consume it, so a filter cannot be applied on screen and
 * forgotten in the file. The source report uses its own bounded date window.
 */
#[MapName(SnakeCaseMapper::class)]
final class LeadFilterData extends SoftDeleteFilterData
{
    /** @var list<string> */
    public const array ALLOWED_FILTERS = [
        'status',
        'record_state',
        'source',
        'region',
        'owner_uuid',
        'emergency',
        'spam',
        'date_from',
        'date_to',
        'converted_from',
        'converted_to',
    ];

    /** @var list<string> */
    public const array SORTABLE = [
        'created_at',
        'updated_at',
        'last_contacted_at',
        'status',
        'source',
        'region',
        'first_name',
        'last_name',
        'email',
    ];

    public function __construct(
        ?string $search = null,
        ?string $dateFrom = null,
        ?string $dateTo = null,
        public readonly ?string $pipelineStatus = null,
        public readonly ?string $recordState = null,
        public readonly ?string $source = null,
        public readonly ?string $region = null,
        public readonly ?string $ownerUuid = null,
        public readonly ?string $emergency = null,
        public readonly ?string $spam = null,
        public readonly ?string $convertedFrom = null,
        public readonly ?string $convertedTo = null,
        public readonly string $sortField = 'created_at',
        public readonly int $sortOrder = -1,
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
            'pipeline_status' => ['nullable', 'string', 'in:'.implode(',', array_column(LeadStatus::cases(), 'value'))],
            'record_state' => ['nullable', 'string', 'in:active,trashed,all'],
            'source' => ['nullable', 'string', 'in:'.implode(',', array_column(LeadSource::cases(), 'value'))],
            'region' => ['nullable', 'string', 'in:'.implode(',', array_column(Region::cases(), 'value'))],
            'owner_uuid' => ['nullable', 'uuid', 'exists:users,uuid'],
            'emergency' => ['nullable', 'string', 'in:yes,no'],
            'spam' => ['nullable', 'string', 'in:spam,ham'],
            'converted_from' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:converted_to'],
            'converted_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:converted_from'],
            'sort_field' => ['required', 'string', 'in:'.implode(',', self::SORTABLE)],
            'sort_order' => ['required', 'integer', 'in:-1,1'],
            'page' => ['required', 'integer', 'min:1', 'max:10000'],
            'per_page' => ['required', 'integer', 'min:1', 'max:100'],
        ];
    }
}
