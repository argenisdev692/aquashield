<?php

declare(strict_types=1);

namespace Modules\ActivityLog\Data;

use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Shared\Application\DTOs\Concerns\DateRangeFilterRules;
use Spatie\LaravelData\Attributes\MapName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

/**
 * Query contract: `search`, `filter[key]`, `sort=-created_at`, `page`, `per_page`.
 * Same shape as the other list screens so `lib/table.ts` drives this table too.
 */
#[MapName(SnakeCaseMapper::class)]
final class ActivityFilterData extends Data
{
    use DateRangeFilterRules;

    /** @var list<string> */
    private const array ALLOWED_FILTERS = ['log_name', 'event', 'subject_type', 'subject_id', 'date_from', 'date_to'];

    /** @var list<string> */
    private const array SORTABLE = ['created_at', 'event', 'log_name'];

    public function __construct(
        public readonly ?string $search = null,
        public readonly ?string $logName = null,
        public readonly ?string $event = null,
        public readonly ?string $subjectType = null,
        public readonly ?string $subjectId = null,
        public readonly ?string $dateFrom = null,
        public readonly ?string $dateTo = null,
        public readonly string $sortField = 'created_at',
        public readonly int $sortOrder = -1,
        public readonly int $page = 1,
        public readonly int $perPage = 15,
    ) {}

    public static function fromRequest(Request $request): self
    {
        $filter = $request->input('filter');

        // A scalar `?filter=backups` must not be treated as "no filters": that
        // silently widens the list beyond what the caller asked for. Malformed
        // input is a 422, like an unknown key.
        if ($filter !== null && ! is_array($filter)) {
            throw ValidationException::withMessages([
                'filter' => 'The filter must be an object of allowlisted keys.',
            ]);
        }

        $filter = is_array($filter) ? $filter : [];

        $unknown = array_diff(array_map('strval', array_keys($filter)), self::ALLOWED_FILTERS);

        if ($unknown !== []) {
            throw ValidationException::withMessages([
                'filter' => 'Unknown filter(s): '.implode(', ', $unknown).'.',
            ]);
        }

        $search = $request->query('search');

        if (is_array($search)) {
            throw ValidationException::withMessages(['search' => 'The search must be a string.']);
        }

        $sort = $request->query('sort', '-created_at');

        if (! is_string($sort)) {
            throw ValidationException::withMessages(['sort' => 'The sort must be a string.']);
        }

        $sort = $sort === '' ? '-created_at' : $sort;

        return self::validateAndCreate([
            'search' => $search,
            'log_name' => $filter['log_name'] ?? null,
            'event' => $filter['event'] ?? null,
            'subject_type' => $filter['subject_type'] ?? null,
            'subject_id' => $filter['subject_id'] ?? null,
            'date_from' => $filter['date_from'] ?? null,
            'date_to' => $filter['date_to'] ?? null,
            'sort_field' => ltrim($sort, '-'),
            'sort_order' => str_starts_with($sort, '-') ? -1 : 1,
            'page' => self::integerQuery($request, 'page', 1),
            'per_page' => self::integerQuery($request, 'per_page', 15),
        ]);
    }

    /**
     * @return array<string, array<int, string>>
     */
    public static function rules(): array
    {
        return [
            ...self::dateRangeRules(),
            'log_name' => ['nullable', 'string', 'max:255'],
            'event' => ['nullable', 'string', 'max:255'],
            'subject_type' => ['nullable', 'string', 'max:255'],
            'subject_id' => ['nullable', 'string', 'max:255'],
            'sort_field' => ['required', 'string', 'in:'.implode(',', self::SORTABLE)],
            'sort_order' => ['required', 'integer', 'in:-1,1'],
            'page' => ['required', 'integer', 'min:1'],
            'per_page' => ['required', 'integer', 'min:1', 'max:100'],
        ];
    }

    private static function integerQuery(Request $request, string $key, int $default): int
    {
        $value = $request->query($key, $default);

        if (! is_numeric($value)) {
            throw ValidationException::withMessages([$key => "The {$key} must be an integer."]);
        }

        return (int) $value;
    }
}
