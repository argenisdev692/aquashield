<?php

declare(strict_types=1);

namespace Modules\LeadAppointment\Application\Dtos;

use Modules\LeadAppointment\Domain\Enums\AvailabilitySource;
use Shared\Application\DTOs\SoftDeleteFilterData;
use Spatie\LaravelData\Attributes\MapName;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

/**
 * Filters for the availability-exception list and its export.
 *
 * The window compares against the exception's own `date` (the day the crew is
 * unavailable), not `created_at`: an operator looking for "what is closed next
 * week" thinks in visit dates, not in the date the row was typed. Source is
 * part of the contract because a synced federal holiday and a manual closure
 * are corrected differently.
 */
#[MapName(SnakeCaseMapper::class)]
final class AvailabilityExceptionFilterData extends SoftDeleteFilterData
{
    /** @var list<string> */
    public const array ALLOWED_FILTERS = [
        'availability',
        'source',
        'record_state',
        'date_from',
        'date_to',
    ];

    /** @var list<string> */
    public const array SORTABLE = ['date', 'created_at'];

    public function __construct(
        ?string $search = null,
        ?string $dateFrom = null,
        ?string $dateTo = null,
        public readonly ?string $availability = null,
        public readonly ?string $source = null,
        public readonly string $recordState = 'active',
        public readonly string $sortField = 'date',
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
            'availability' => ['nullable', 'string', 'in:open,closed'],
            'source' => ['nullable', 'string', 'in:'.implode(',', array_column(AvailabilitySource::cases(), 'value'))],
            'record_state' => ['required', 'string', 'in:active,trashed,all'],
            'sort_field' => ['required', 'string', 'in:'.implode(',', self::SORTABLE)],
            'sort_order' => ['required', 'integer', 'in:-1,1'],
            'page' => ['required', 'integer', 'min:1'],
            'per_page' => ['required', 'integer', 'min:1', 'max:100'],
        ];
    }
}
