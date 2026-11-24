<?php

declare(strict_types=1);

namespace Modules\LeadAppointment\Http\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Modules\LeadAppointment\Domain\Services\EmailSpamGuard;

/**
 * Hard reject for placeholder and disposable domains on the public form. Role
 * mailboxes are not rejected here; the action scores them for review.
 */
final readonly class NotBlockedEmail implements ValidationRule
{
    public function __construct(private EmailSpamGuard $guard) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || trim($value) === '') {
            return;
        }

        if ($this->guard->isBlocked($value)) {
            $fail('Use a real email address. Placeholder and disposable domains are not accepted.');
        }
    }
}
