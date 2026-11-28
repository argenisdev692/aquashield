<?php

declare(strict_types=1);

namespace Modules\LeadAppointment\Http\Support;

use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Modules\LeadAppointment\Application\Dtos\LeadFilterData;

/**
 * Transport adapter for the list/export filter contract. The Application DTO
 * has no `Request` dependency; this factory is the one place that knows the
 * DataTable kit's `search` / `filter[key]` / `sort` wire shape.
 */
final readonly class LeadFilterFactory
{
    public static function fromRequest(Request $request): LeadFilterData
    {
        $filter = $request->input('filter');
        $filter = is_array($filter) ? $filter : [];

        $unknown = array_diff(array_map('strval', array_keys($filter)), LeadFilterData::ALLOWED_FILTERS);

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

        if (! is_string($sort) || $sort === '') {
            $sort = '-created_at';
        }

        return LeadFilterData::validateAndCreate([
            'search' => $search,
            'date_from' => $filter['date_from'] ?? null,
            'date_to' => $filter['date_to'] ?? null,
            'pipeline_status' => $filter['status'] ?? null,
            'record_state' => $filter['record_state'] ?? null,
            'source' => $filter['source'] ?? null,
            'region' => $filter['region'] ?? null,
            'owner_uuid' => $filter['owner_uuid'] ?? null,
            'emergency' => $filter['emergency'] ?? null,
            'spam' => $filter['spam'] ?? null,
            'converted_from' => $filter['converted_from'] ?? null,
            'converted_to' => $filter['converted_to'] ?? null,
            'sort_field' => ltrim($sort, '-'),
            'sort_order' => str_starts_with($sort, '-') ? -1 : 1,
            'page' => self::integerQuery($request, 'page', 1),
            'per_page' => self::integerQuery($request, 'per_page', 15),
        ]);
    }

    private static function integerQuery(Request $request, string $key, int $default): int
    {
        $value = $request->query($key, $default);

        if (! is_int($value) && ! is_string($value)) {
            throw ValidationException::withMessages([$key => "The {$key} must be an integer."]);
        }

        return (int) $value;
    }
}
