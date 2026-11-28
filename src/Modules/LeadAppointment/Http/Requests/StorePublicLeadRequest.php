<?php

declare(strict_types=1);

namespace Modules\LeadAppointment\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\LeadAppointment\Domain\Enums\Region;
use Modules\LeadAppointment\Domain\Services\EmailSpamGuard;
use Modules\LeadAppointment\Http\Requests\Concerns\NormalizesLegacyLeadInput;
use Modules\LeadAppointment\Http\Rules\NotBlockedEmail;

/**
 * Public website form. The landing page posts snake_case JSON; the honeypot
 * fields are intentionally not listed here (SpamProtection reads the whole
 * request) and `source` is server-set, never client-provided.
 */
final class StorePublicLeadRequest extends FormRequest
{
    use NormalizesLegacyLeadInput;

    protected function prepareForValidation(): void
    {
        $this->normalizeLegacyLeadInput();
    }

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
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'company_name' => ['nullable', 'string', 'max:255'],
            'email' => [
                'nullable',
                'email',
                'max:255',
                'required_without:phone',
                new NotBlockedEmail(app(EmailSpamGuard::class)),
            ],
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
            'is_emergency' => ['sometimes', 'boolean'],
            'sms_consent' => ['sometimes', 'boolean'],
            'consent_text_version' => ['nullable', 'string', 'max:20', 'required_if:sms_consent,true'],
            'preferred_language' => ['nullable', 'string', 'in:en,es'],
            'source_page' => ['nullable', 'string', 'max:255'],
            'utm_source' => ['nullable', 'string', 'max:255'],
            'utm_medium' => ['nullable', 'string', 'max:255'],
            'utm_campaign' => ['nullable', 'string', 'max:255'],
            'utm_content' => ['nullable', 'string', 'max:255'],
            'utm_term' => ['nullable', 'string', 'max:255'],
        ];
    }
}
