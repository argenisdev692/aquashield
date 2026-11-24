<?php

declare(strict_types=1);

namespace Modules\Users\Data;

use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Modules\Users\Enums\UserStatus;
use Shared\Application\DTOs\SoftDeleteFilterData;
use Spatie\LaravelData\Attributes\MapName;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

/**
 * The list/export query contract for the Users module.
 *
 * Two status axes, deliberately separate:
 *
 *  - `status` is the soft-delete axis (`active`, `trashed`, `all`);
 *  - `account_status` is the staff lifecycle (`active`, `invited`, `suspended`).
 *
 * Collapsing them was the original bug: "show suspended accounts" and "show
 * deleted accounts" are different questions and a single `status` key cannot
 * answer both.
 *
 * `role` is a list so the advanced filter can select several roles at once; a
 * scalar legacy link is normalised to a one-item list.
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
        'account_status',
        'role',
        'permission',
        'date_from',
        'date_to',
    ];

    /**
     * Real columns only. `name` is an accessor over first_name/last_name and is
     * expanded to the two columns by UserListQuery; the allowlist is the guard
     * that prevents an arbitrary orderBy from user input.
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
        'name',
        'username',
        'phone',
        'status',
    ];

    /**
     * @param list<string> $role
     */
    public function __construct(
        ?string $search = null,
        ?string $status = null,
        ?string $dateFrom = null,
        ?string $dateTo = null,
        public readonly ?string $accountStatus = null,
        public readonly array $role = [],
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

        // A scalar `?filter=status` used to be treated as "no filters" and
        // silently widened the list. Malformed input is a 422, like an unknown
        // key, so the caller never receives more rows than it asked for.
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
            'role' => self::normaliseRole($filter['role'] ?? null),
            'sort_field' => ltrim($sort, '-'),
            'sort_order' => str_starts_with($sort, '-') ? -1 : 1,
            'page' => self::integerQuery($request, 'page', 1),
            'per_page' => self::integerQuery($request, 'per_page', 15),
        ]);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public static function rules(): array
    {
        return [
            ...self::baseRules(),
            'status' => ['nullable', 'string', 'in:active,trashed,all'],
            // Derived from the enum, so adding a lifecycle state cannot leave
            // the filter silently rejecting it.
            'account_status' => ['nullable', 'string', Rule::enum(UserStatus::class)],
            // Capped: every item runs an `exists` query, so an uncapped array
            // is an N-query request (OWASP API4).
            'role' => ['nullable', 'array', 'max:50'],
            'role.*' => ['string', 'distinct', 'exists:roles,name'],
            'permission' => ['nullable', 'string', 'max:255', 'exists:permissions,name'],
            'sort_field' => ['required', 'string', 'in:'.implode(',', self::SORTABLE)],
            'sort_order' => ['required', 'integer', 'in:-1,1'],
            'page' => ['required', 'integer', 'min:1'],
            'per_page' => ['required', 'integer', 'min:1', 'max:100'],
        ];
    }

    /**
     * A legacy `filter[role]=MANAGER` link keeps working; the advanced filter
     * sends `filter[role][]`.
     *
     * @return list<string>
     */
    private static function normaliseRole(mixed $value): array
    {
        if ($value === null || $value === '') {
            return [];
        }

        if (is_string($value)) {
            return [$value];
        }

        if (! is_array($value)) {
            throw ValidationException::withMessages([
                'filter.role' => 'The role filter must be a role name or a list of names.',
            ]);
        }

        $roles = [];

        foreach ($value as $role) {
            if (! is_string($role)) {
                throw ValidationException::withMessages([
                    'filter.role' => 'Every role filter value must be a role name.',
                ]);
            }

            $roles[] = $role;
        }

        return array_values(array_unique($roles));
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
