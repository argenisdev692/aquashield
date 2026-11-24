<?php

declare(strict_types=1);

namespace Modules\LeadAppointment\Domain\ValueObjects;

/**
 * US phone normalisation at the domain boundary. Ten digits become +1XXXXXXXXXX;
 * an existing +1 number is kept; anything unrecognised is returned trimmed so a
 * lead is never lost because of a formatting preference.
 */
final readonly class PhoneNumber
{
    private function __construct(public string $value) {}

    public static function fromString(?string $raw): ?self
    {
        if ($raw === null) {
            return null;
        }

        $trimmed = trim($raw);

        if ($trimmed === '') {
            return null;
        }

        $digits = (string) preg_replace('/\D+/', '', $trimmed);

        if (strlen($digits) === 10) {
            return new self('+1'.$digits);
        }

        if (strlen($digits) === 11 && str_starts_with($digits, '1')) {
            return new self('+'.$digits);
        }

        return new self($trimmed);
    }
}
