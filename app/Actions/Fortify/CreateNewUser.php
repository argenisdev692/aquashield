<?php

declare(strict_types=1);

namespace App\Actions\Fortify;

use App\Models\User;
use App\Rules\Username;
use App\Support\UserProfileInput;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules;

    /**
     * Validate and create a newly registered user.
     *
     * `name` remains accepted as a backwards-compatible alias for the split
     * first/last pair; the registration screen sends the canonical fields.
     *
     * @param array<string, mixed> $input
     *
     * @throws ValidationException
     */
    public function create(array $input): User
    {
        $input = $this->normalise($input);

        $rules = [
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['nullable', 'string', 'max:255'],
            'username' => [
                'nullable',
                'string',
                new Username,
                Rule::unique(User::class),
            ],
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique(User::class),
            ],
            'password' => $this->passwordRules(),
            'terms_and_conditions' => ['sometimes', 'boolean'],
        ];

        if (array_key_exists('name', $input)) {
            $rules['name'] = ['required', 'string', 'max:255'];
        }

        $validated = Validator::make($input, $rules)->validate();

        return User::create([
            'first_name' => $validated['first_name'],
            'last_name' => $validated['last_name'] ?? null,
            'username' => $validated['username'] ?? null,
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'terms_and_conditions' => (bool) ($validated['terms_and_conditions'] ?? false),
            'registered_by_id' => $this->registeredById(),
        ]);
    }

    /**
     * A user created by someone else records the authenticated creator.
     *
     * Public self-registration has no creator and leaves the nullable column
     * empty. If an authenticated user is present, they must hold the module
     * permission; a user without CREATE_USER can never mint another account.
     *
     * @throws AuthorizationException
     */
    private function registeredById(): ?int
    {
        $creator = auth()->user();

        if (! $creator instanceof User) {
            return null;
        }

        if (! $creator->can('CREATE_USER')) {
            throw new AuthorizationException(__('You do not have permission to create users.'));
        }

        return $creator->getKey();
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

        $input['terms_and_conditions'] = (bool) ($input['terms_and_conditions'] ?? false);

        return $input;
    }
}
