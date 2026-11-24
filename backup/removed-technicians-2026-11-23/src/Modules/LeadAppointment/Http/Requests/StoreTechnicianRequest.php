<?php

declare(strict_types=1);

namespace Modules\LeadAppointment\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\LeadAppointment\Domain\Enums\Region;

final class StoreTechnicianRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'region' => ['required', Rule::enum(Region::class)],
            'user_uuid' => ['nullable', 'uuid', 'exists:users,uuid'],
            'phone' => ['nullable', 'string', 'max:32'],
            'email' => ['nullable', 'email', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
            'daily_capacity' => ['required', 'integer', 'min:1', 'max:20'],
            'travel_buffer_minutes' => ['required', 'integer', 'min:0', 'max:120'],
        ];
    }
}
