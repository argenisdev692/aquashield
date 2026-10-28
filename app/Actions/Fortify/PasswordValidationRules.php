<?php

declare(strict_types=1);

namespace App\Actions\Fortify;

use Illuminate\Contracts\Validation\Rule;
use Illuminate\Validation\Rules\Password;

trait PasswordValidationRules
{
    /**
     * The product password policy: six to ten characters.
     *
     * Deliberately explicit instead of Password::default(): the default is
     * environment-dependent (and production adds `uncompromised()`), which
     * would make registration, reset and profile updates disagree. This trait
     * is the single server-side source of the rule; the Vue schemas mirror it
     * in `resources/js/lib/auth.ts` because the browser needs the same hint.
     *
     * A ten-character ceiling is a product decision, not an OWASP recommendation
     * (the baseline asks for 12+). It is recorded as accepted debt in the auth
     * review; raising it is a one-line change here plus the mirrored TS constant.
     *
     * @return array<int, Rule|array<mixed>|string>
     */
    protected function passwordRules(): array
    {
        return ['required', 'string', Password::min(6)->max(10), 'confirmed'];
    }
}
