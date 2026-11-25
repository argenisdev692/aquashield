<?php

declare(strict_types=1);

namespace Modules\LeadAppointment\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Shared\Domain\Enums\Permission;

final class BulkRangeExceptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(Permission::CreateAvailabilityException->value) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'from' => ['required', 'date_format:Y-m-d'],
            'to' => ['required', 'date_format:Y-m-d', 'after_or_equal:from'],
            'is_available' => ['sometimes', 'boolean'],
            'start_time' => ['nullable', 'date_format:H:i', 'required_if:is_available,true'],
            'end_time' => ['nullable', 'date_format:H:i', 'required_if:is_available,true', 'after:start_time'],
            'reason' => ['nullable', 'string', 'max:255'],
        ];
    }
}
