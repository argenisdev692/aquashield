<?php

declare(strict_types=1);

namespace Modules\LeadAppointment\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\LeadAppointment\Domain\Enums\AppointmentKind;
use Modules\LeadAppointment\Domain\Enums\Region;
use Shared\Domain\Enums\Permission;

final class StoreAppointmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(Permission::CreateAppointment->value) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'lead_uuid' => ['required', 'uuid', Rule::exists('leads', 'uuid')->whereNull('deleted_at')],
            'kind' => ['required', Rule::enum(AppointmentKind::class)],
            'starts_at' => ['required', 'date'],
            'duration_minutes' => ['nullable', 'integer', 'min:15', 'max:480'],
            'timezone' => ['nullable', 'timezone'],
            'region' => ['nullable', Rule::enum(Region::class)],
            'notes' => ['nullable', 'string', 'max:5000'],
            'is_emergency' => ['sometimes', 'boolean'],
        ];
    }
}
