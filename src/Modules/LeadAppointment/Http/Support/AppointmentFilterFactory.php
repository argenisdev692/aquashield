<?php

declare(strict_types=1);

namespace Modules\LeadAppointment\Http\Support;

use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Modules\LeadAppointment\Application\Dtos\AppointmentFilterData;

final readonly class AppointmentFilterFactory
{
    public static function fromRequest(Request $request): AppointmentFilterData
    {
        $filter = $request->input('filter');
        $filter = is_array($filter) ? $filter : [];

        $unknown = array_diff(array_map('strval', array_keys($filter)), AppointmentFilterData::ALLOWED_FILTERS);

        if ($unknown !== []) {
            throw ValidationException::withMessages([
                'filter' => 'Unknown filter(s): '.implode(', ', $unknown).'.',
            ]);
        }

        $search = $request->query('search');
        $sort = $request->query('sort', 'starts_at');

        if (is_array($search) || ! is_string($sort) || $sort === '') {
            throw ValidationException::withMessages(['sort' => 'Invalid list parameters.']);
        }

        return AppointmentFilterData::validateAndCreate([
            'search' => $search,
            'date_from' => $filter['date_from'] ?? null,
            'date_to' => $filter['date_to'] ?? null,
            'appointment_status' => $filter['status'] ?? null,
            'record_state' => $filter['record_state'] ?? null,
            'kind' => $filter['kind'] ?? null,
            'region' => $filter['region'] ?? null,
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
