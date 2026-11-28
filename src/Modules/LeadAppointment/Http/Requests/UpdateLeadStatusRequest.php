<?php

declare(strict_types=1);

namespace Modules\LeadAppointment\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\LeadAppointment\Domain\Enums\InspectionStatus;
use Modules\LeadAppointment\Domain\Enums\LeadStatus;
use Shared\Domain\Enums\Permission;

final class UpdateLeadStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user->can(Permission::UpdateLead->value) === true
            && $user->can(Permission::ViewLead->value) === true;
    }

    /**
     * Accept the legacy `status_lead` / `Called` / `Pending` / `Declined`
     * vocabulary at the transport boundary. The domain keeps the new enum.
     */
    protected function prepareForValidation(): void
    {
        $status = $this->input('status', $this->input('status_lead'));

        if (is_string($status)) {
            $normalized = mb_strtolower(trim($status));

            $this->merge([
                'status' => in_array($normalized, ['new', 'called', 'pending', 'declined'], true)
                    ? LeadStatus::fromLegacy($normalized)->value
                    : $normalized,
            ]);
        }

        $inspectionStatus = $this->input('inspection_status');

        if (is_string($inspectionStatus)) {
            $this->merge(['inspection_status' => mb_strtolower(trim($inspectionStatus))]);
        }
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'status' => ['required', Rule::enum(LeadStatus::class)],
            'note' => ['nullable', 'string', 'max:2000'],
            'inspection_date' => ['nullable', 'date_format:Y-m-d'],
            'inspection_time' => ['nullable', 'date_format:H:i'],
            'inspection_status' => ['nullable', Rule::enum(InspectionStatus::class)],
        ];
    }
}
