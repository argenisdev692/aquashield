<?php

declare(strict_types=1);

namespace Modules\Users\Data;

use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Shared\Application\DTOs\SoftDeleteFilterData;
use Spatie\LaravelData\Attributes\MapName;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

/**
 * The list/export query contract for the Users module.
 *
 * The DataTable kit serialises `search` and `filter[key]` at the top level and
 * `sort` / `page` / `per_page` beside them. fromRequest() is the one adapter
 * between that wire shape and this DTO, so the list endpoint and the three
 * export formats cannot drift.
 *
 * `status` is `active` (default), `trashed`, or `all`; the model's SoftDeletes
 * global scope already excludes trashed rows, so `active` needs no clause.
 */
#[MapName(SnakeCaseMapper::class)]
final class UserFilterData extends SoftDeleteFilterData
{
    /**
     * Filter keys the DataTable is allowed to send. An unknown key is a 422,
     * not a silent no-op that makes a filtered view look empty for the wrong
     * reason.
     *
     * @var list<string>
     */
    private const array ALLOWED_FILTERS = [
        'status',
        'role',
        'permission',
        'date_from',
        'date_to',
    ];

    /**
     * Real columns only. `name` is an accessor over first_name/last_name, and
     * ordering by it would be a database error — the allowlist is the guard.
     *
     * @var list<string>
     */
    private const array SORTABLE = [
        'created_at',
        'updated_at',
        'deleted_at',
        'email',
        'first_name',
        'last_name',
        'username',
        'phone',
    ];

    public function __construct(
        ?string $search = null,
        ?string $status = null,
        ?string $dateFrom = null,
        ?string $dateTo = null,
        public readonly ?string $role = null,
        public readonly ?string $permission = null,
        public readonly string $sortField = 'created_at',
        public readonly int $sortOrder = -1,
        public readonly int $page = 1,
        public readonly int $perPage = 15,
    ) {
        parent::__construct($search, $status, $dateFrom, $dateTo);
    }

    public static function fromRequest(Request $request): self
    {
        $filter = $request->input('filter');
        $filter = is_array($filter) ? $filter : [];

        $unknown = array_diff(array_map('strval', array_keys($filter)), self::ALLOWED_FILTERS);

        if ($unknown !== []) {
            throw ValidationException::withMessages([
                'filter' => 'Unknown filter(s): '.implode(', ', $unknown).'.',
            ]);
        }

        $search = $request->query('search');

        if (is_array($search)) {
            throw ValidationException::withMessages([
                'search' => 'The search must be a string.',
            ]);
        }

        $sort = $request->query('sort', '-created_at');

        if (! is_string($sort)) {
            throw ValidationException::withMessages([
                'sort' => 'The sort must be a string.',
            ]);
        }

        $sort = $sort === '' ? '-created_at' : $sort;

        return self::validateAndCreate([
            'search' => $search,
            ...$filter,
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
            ...self::baseRules(),
            'status' => ['nullable', 'string', 'in:active,trashed,all'],
            'role' => ['nullable', 'string', 'max:255', 'exists:roles,name'],
            'permission' => ['nullable', 'string', 'max:255', 'exists:permissions,name'],
            'sort_field' => ['required', 'string', 'in:'.implode(',', self::SORTABLE)],
            'sort_order' => ['required', 'integer', 'in:-1,1'],
            'page' => ['required', 'integer', 'min:1'],
            'per_page' => ['required', 'integer', 'min:1', 'max:100'],
        ];
    }

    private static function integerQuery(Request $request, string $key, int $default): int
    {
        $value = $request->query($key, $default);

        if (! is_int($value) && ! is_string($value)) {
            throw ValidationException::withMessages([
                $key => "The {$key} must be an integer.",
            ]);
        }

        return (int) $value;
    }
}
