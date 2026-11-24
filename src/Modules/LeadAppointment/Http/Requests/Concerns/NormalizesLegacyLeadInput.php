<?php

declare(strict_types=1);

namespace Modules\LeadAppointment\Http\Requests\Concerns;

/**
 * Accepts the column names of the legacy `appointments` intake table without
 * renaming the normalized schema. The canonical names stay `zip_code` and
 * `damage_description`; the aliases are transport-only.
 */
trait NormalizesLegacyLeadInput
{
    protected function normalizeLegacyLeadInput(): void
    {
        $aliases = [
            'zipcode' => 'zip_code',
            'damage_detail' => 'damage_description',
        ];

        foreach ($aliases as $legacy => $canonical) {
            if ($this->has($legacy) && ! $this->has($canonical)) {
                $this->merge([$canonical => $this->input($legacy)]);
            }
        }

        $inspectionStatus = $this->input('inspection_status');

        if (is_string($inspectionStatus)) {
            $this->merge(['inspection_status' => mb_strtolower(trim($inspectionStatus))]);
        }

        foreach (['insurance_property', 'intent_to_claim', 'sms_consent', 'is_emergency'] as $field) {
            if (! $this->has($field) || is_bool($this->input($field))) {
                continue;
            }

            $value = $this->input($field);

            if (! is_string($value)) {
                continue;
            }

            $normalized = mb_strtolower(trim($value));

            if (in_array($normalized, ['yes', 'true', '1', 'on'], true)) {
                $this->merge([$field => true]);
            } elseif (in_array($normalized, ['no', 'false', '0', 'off'], true)) {
                $this->merge([$field => false]);
            }
        }

        if ($this->boolean('sms_consent') && ! $this->has('consent_text_version')) {
            $this->merge([
                'consent_text_version' => config('lead-appointment.sms_consent_version'),
            ]);
        }
    }
}
