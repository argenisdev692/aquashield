<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Canonical normalisation for user profile input.
 *
 * The Fortify registration/profile actions and the Users module's admin
 * requests all accept the same snake_case payload. Normalising it in one place
 * keeps the wire rules identical: e-mail and username are lower-cased and
 * trimmed, optional text becomes null (not ""), and date/latitude/longitude
 * empty strings become null before validation.
 *
 * This is transport normalisation, not a business rule: the validation rules
 * stay in each FormRequest, which is the authoritative boundary.
 */
final class UserProfileInput
{
    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public static function normalise(array $input): array
    {
        $input['email'] = Str::lower(trim((string) ($input['email'] ?? '')));

        foreach ([
            'first_name',
            'last_name',
            'phone',
            'address',
            'address_2',
            'zip_code',
            'city',
            'state',
            'country',
            'gender',
        ] as $key) {
            if (! array_key_exists($key, $input)) {
                continue;
            }

            $value = trim((string) $input[$key]);
            $input[$key] = $value === '' ? null : $value;
        }

        if (array_key_exists('username', $input)) {
            $username = Str::lower(trim((string) $input['username']));
            $input['username'] = $username === '' ? null : $username;
        }

        foreach (['date_of_birth', 'latitude', 'longitude'] as $key) {
            if (array_key_exists($key, $input) && $input[$key] === '') {
                $input[$key] = null;
            }
        }

        return $input;
    }
}
