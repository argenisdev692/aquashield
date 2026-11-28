<?php

declare(strict_types=1);

namespace Modules\LeadAppointment\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Shared\Domain\Enums\Permission;

final class RescheduleAppointmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user->can(Permission::UpdateAppointment->value) === true
            && $user->can(Permission::ViewAppointment->value) === true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'starts_at' => ['required', 'date'],
            'duration_minutes' => ['nullable', 'integer', 'min:15', 'max:480'],
            'timezone' => ['nullable', 'timezone'],
            'reason' => ['required', 'string', 'max:500'],
        ];
    }
}
