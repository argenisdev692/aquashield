<?php

declare(strict_types=1);

namespace Modules\Users\Http\Requests;

use App\Models\User;
use App\Rules\AllowedEmailDomain;
use App\Rules\Username;
use App\Support\UserProfileInput;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreUserRequest extends FormRequest
{
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
                new AllowedEmailDomain(),
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
            'roles' => ['nullable', 'array'],
            'roles.*' => ['string', 'distinct', 'exists:roles,name'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', 'distinct', 'exists:permissions,name'],
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
    }
}
