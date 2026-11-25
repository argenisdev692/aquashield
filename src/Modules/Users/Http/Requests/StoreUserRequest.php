<?php

declare(strict_types=1);

namespace Modules\Users\Http\Requests;

use App\Actions\Fortify\PasswordValidationRules;
use App\Models\User;
use App\Rules\AllowedEmailDomain;
use App\Rules\Username;
use App\Support\UserProfileInput;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Shared\Application\Rules\ExistingPermissionNames;
use Shared\Application\Rules\ExistingRoleNames;

final class StoreUserRequest extends FormRequest
{
    use PasswordValidationRules;

    public function authorize(): bool
    {
        return $this->user()?->can('create', User::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
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
                new AllowedEmailDomain,
                Rule::unique(User::class),
            ],
            'phone' => ['nullable', 'string', 'max:32'],
            'date_of_birth' => ['nullable', 'date_format:Y-m-d', 'before:today'],
            'address' => ['nullable', 'string', 'max:255'],
            'address_2' => ['nullable', 'string', 'max:255'],
            'zip_code' => ['nullable', 'string', 'max:32'],
            'city' => ['nullable', 'string', 'max:120'],
            'state' => ['nullable', 'string', 'max:120'],
            'country' => ['nullable', 'string', 'max:120'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'gender' => ['nullable', 'string', 'in:male,female,other', 'max:50'],
            // Both arrays are bounded AND resolved in one query each: an
            // `exists` per item is an N-query request when the UI submits the
            // whole selection (OWASP API4). 50 covers the 26 seeded roles, 600
            // covers the 505 seeded permissions.
            'roles' => ['nullable', 'array', 'max:50', new ExistingRoleNames],
            'roles.*' => ['string', 'distinct'],
            'permissions' => ['nullable', 'array', 'max:600', new ExistingPermissionNames],
            'permissions.*' => ['string', 'distinct'],
            'send_invitation' => ['sometimes', 'boolean'],
            'temporary_password' => [
                'nullable',
                'string',
                'required_if:send_invitation,false,0',
                // One policy, defined in PasswordValidationRules and used by
                // Fortify, activation and this admin create form alike.
                $this->passwordRule(),
                'confirmed',
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(UserProfileInput::normalise($this->all()));

        // A missing access list means "no roles / no direct permissions".
        // A non-array value is left alone so the `array` rule returns 422.
        if ($this->input('roles') === null) {
            $this->merge(['roles' => []]);
        }

        if ($this->input('permissions') === null) {
            $this->merge(['permissions' => []]);
        }

        if (! $this->has('send_invitation')) {
            $this->merge(['send_invitation' => true]);
        }
    }
}
