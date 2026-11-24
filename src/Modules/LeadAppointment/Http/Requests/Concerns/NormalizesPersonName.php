<?php

declare(strict_types=1);

namespace Modules\LeadAppointment\Http\Requests\Concerns;

use Illuminate\Support\Str;

/**
 * Admin-entered person names are Title Cased after whitespace is collapsed.
 * The `PersonName` rule is the gate that rejects digits and punctuation; this
 * concern only fixes the casing and the spacing of a valid name.
 */
trait NormalizesPersonName
{
    protected function normalizePersonNames(): void
    {
        $names = [];

        foreach (['first_name', 'last_name'] as $field) {
            $value = $this->input($field);

            if (is_string($value)) {
                $names[$field] = Str::of($value)->squish()->title()->toString();
            }
        }

        if ($names !== []) {
            $this->merge($names);
        }
    }
}
