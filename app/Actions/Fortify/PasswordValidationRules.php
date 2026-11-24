<?php

declare(strict_types=1);

namespace App\Actions\Fortify;

use Illuminate\Contracts\Validation\Rule;
use Illuminate\Validation\Rules\Password;

trait PasswordValidationRules
{
    /**
     * The product password policy: eight to fifteen characters.
     *
     * Deliberately explicit instead of Password::default(): the default is
     * environment-dependent (and production adds `uncompromised()`), which
     * would make registration, reset and profile updates disagree. This trait
     * is the single server-side source of the rule; the frontend schema mirrors
     * it in `resources/js/lib/auth.ts` because the browser needs the same hint.
     *
     * A fifteen-character ceiling is a product decision, not an OWASP
     * recommendation (NIST SP 800-63B-4 asks for 15 as the sole factor and a
     * 64-character maximum). It is recorded as accepted debt in the auth
     * review; raising it is a one-line change here plus the mirrored TS constant.
     *
     * @return array<int, Rule|array<mixed>|string>
     */
    protected function passwordRules(): array
    {
        return ['required', 'string', $this->passwordRule(), 'confirmed'];
    }

    /**
     * The bare policy, for requests where the password is conditional
     * (for example the admin create form's optional temporary password).
     */
    protected function passwordRule(): Password
    {
        return Password::min(8)->max(15);
    }
}
