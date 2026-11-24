<?php

declare(strict_types=1);

namespace Modules\LeadAppointment\Http\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * A person name: Unicode letters and single spaces only, 50 characters
 * including spaces. Accents are letters; digits, punctuation and extra
 * whitespace are rejected. The admin requests normalize to Title Case before
 * this rule runs, so the stored value and the validated value agree.
 */
final readonly class PersonName implements ValidationRule
{
    public const MAX_LENGTH = 50;

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            return;
        }

        if (mb_strlen($value) > self::MAX_LENGTH) {
            $fail('The :attribute may not be longer than 50 characters, spaces included.');

            return;
        }

        if (preg_match('/^[\p{L}]+(?: [\p{L}]+)*$/u', $value) !== 1) {
            $fail('The :attribute may contain letters and spaces only.');
        }
    }
}
