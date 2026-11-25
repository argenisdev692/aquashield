<?php

declare(strict_types=1);

namespace Modules\LeadAppointment\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\LeadAppointment\Domain\Enums\InspectionStatus;
use Modules\LeadAppointment\Domain\Enums\Region;
use Modules\LeadAppointment\Http\Requests\Concerns\NormalizesLegacyLeadInput;
use Modules\LeadAppointment\Http\Requests\Concerns\NormalizesPersonName;
use Modules\LeadAppointment\Http\Rules\PersonName;
use Shared\Domain\Enums\Permission;

final class StoreLeadRequest extends FormRequest
{
    use NormalizesLegacyLeadInput, NormalizesPersonName;

    protected function prepareForValidation(): void
    {
        $this->normalizeLegacyLeadInput();
        $this->normalizePersonNames();
    }

    public function authorize(): bool
    {
        return $this->user()?->can(Permission::CreateLead->value) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'first_name' => ['required', 'string', new PersonName],
            'last_name' => ['required', 'string', new PersonName],
            'company_name' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255', 'required_without:phone'],
            'phone' => ['nullable', 'string', 'max:32', 'regex:/^\+?[0-9\s().-]{7,32}$/', 'required_without:email'],
            'address' => ['nullable', 'string', 'max:255'],
            'address_2' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:120'],
            'state' => ['nullable', 'string', 'size:2', 'in:TX,FL'],
            'zip_code' => ['nullable', 'string', 'regex:/^\d{5}(-\d{4})?$/'],
            'region' => ['nullable', Rule::enum(Region::class)],
            'damage_description' => ['nullable', 'string', 'max:5000'],
            'insurance_property' => ['sometimes', 'boolean'],
            'message' => ['nullable', 'string', 'max:5000'],
            'intent_to_claim' => ['nullable', 'boolean'],
            'follow_up_date' => ['nullable', 'date_format:Y-m-d'],
            'inspection_date' => ['nullable', 'date_format:Y-m-d'],
            'inspection_time' => ['nullable', 'date_format:H:i'],
            'inspection_status' => ['nullable', Rule::enum(InspectionStatus::class)],
            'additional_note' => ['nullable', 'string', 'max:5000'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'is_emergency' => ['sometimes', 'boolean'],
            'sms_consent' => ['sometimes', 'boolean'],
            'consent_text_version' => ['nullable', 'string', 'max:20'],
            'preferred_language' => ['nullable', 'string', 'in:en,es'],
            'owner_uuid' => ['nullable', 'uuid', 'exists:users,uuid'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
