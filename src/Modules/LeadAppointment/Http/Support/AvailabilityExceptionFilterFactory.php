<?php

declare(strict_types=1);

namespace Modules\LeadAppointment\Http\Support;

use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Modules\LeadAppointment\Application\Dtos\AvailabilityExceptionFilterData;

/**
 * Reads the availability list contract from the URL.
 *
 * The table and its export share this factory, so a filtered CSV can never
 * cover a different window than the screen it was downloaded from.
 */
final readonly class AvailabilityExceptionFilterFactory
{
    public static function fromRequest(Request $request): AvailabilityExceptionFilterData
    {
        $filter = $request->input('filter');
        $filter = is_array($filter) ? $filter : [];

        $unknown = array_diff(
            array_map('strval', array_keys($filter)),
            AvailabilityExceptionFilterData::ALLOWED_FILTERS,
        );

        if ($unknown !== []) {
            throw ValidationException::withMessages([
                'filter' => 'Unknown filter(s): '.implode(', ', $unknown).'.',
            ]);
        }

        $search = $request->query('search');
        $sort = $request->query('sort', 'date');

        if (is_array($search) || ! is_string($sort) || $sort === '') {
            throw ValidationException::withMessages(['sort' => 'Invalid list parameters.']);
        }

        return AvailabilityExceptionFilterData::validateAndCreate([
            'search' => $search,
            'date_from' => $filter['date_from'] ?? null,
            'date_to' => $filter['date_to'] ?? null,
            'availability' => $filter['availability'] ?? null,
            'source' => $filter['source'] ?? null,
            'record_state' => $filter['record_state'] ?? 'active',
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
