<?php

declare(strict_types=1);

namespace App\Actions\Fortify;

use App\Models\User;
use App\Rules\AllowedEmailDomain;
use App\Rules\Username;
use App\Support\UserProfileInput;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\UpdatesUserProfileInformation;

class UpdateUserProfileInformation implements UpdatesUserProfileInformation
{
    /**
     * Validate and update the given user's profile information.
     *
     * `name` remains accepted as a backwards-compatible alias for the split
     * first/last pair; the account screen sends the canonical fields.
     *
     * @param array<string, mixed> $input
     *
     * @throws ValidationException
     */
    public function update(User $user, array $input): void
    {
        $input = $this->normalise($input);

        $rules = [
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['nullable', 'string', 'max:255'],
            'username' => [
                'nullable',
                'string',
                new Username,
                Rule::unique('users', 'username')->ignore($user->getKey()),
            ],
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                new AllowedEmailDomain(),
                Rule::unique('users', 'email')->ignore($user->getKey()),
            ],
            'phone' => ['nullable', 'string', 'max:32'],
            'date_of_birth' => ['nullable', 'date', 'before:today'],
            'address' => ['nullable', 'string', 'max:255'],
            'address_2' => ['nullable', 'string', 'max:255'],
            'zip_code' => ['nullable', 'string', 'max:32'],
            'city' => ['nullable', 'string', 'max:120'],
            'state' => ['nullable', 'string', 'max:120'],
            'country' => ['nullable', 'string', 'max:120'],
            'gender' => ['nullable', 'string', 'in:male,female,other', 'max:50'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'terms_and_conditions' => ['sometimes', 'boolean'],
        ];

        if (array_key_exists('name', $input)) {
            $rules['name'] = ['required', 'string', 'max:255'];
        }

        $validated = Validator::make($input, $rules)->validateWithBag('updateProfileInformation');

        unset($validated['name']);
        $validated['email'] = Str::lower($validated['email']);

        if ($validated['email'] !== $user->email) {
            $user->forceFill([
                ...$validated,
                'email_verified_at' => null,
            ])->save();

            $user->sendEmailVerificationNotification();

            return;
        }

        $user->forceFill($validated)->save();
    }

    /**
     * Normalise transport quirks before validation.
     *
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    private function normalise(array $input): array
    {
        $input = UserProfileInput::normalise($input);

        if (array_key_exists('name', $input) && ! array_key_exists('first_name', $input)) {
            $parts = preg_split('/\s+/', trim((string) $input['name'])) ?: [];
            $input['first_name'] = array_shift($parts) ?? '';
            $input['last_name'] = $parts === [] ? null : implode(' ', $parts);
        }

        if (array_key_exists('terms_and_conditions', $input)) {
            $input['terms_and_conditions'] = (bool) $input['terms_and_conditions'];
        }

        return $input;
    }
}
