<?php

declare(strict_types=1);

namespace Modules\Users\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Shared\Domain\Enums\Permission as PermissionName;

/**
 * Transport validation for the debounced uniqueness probe.
 *
 * The endpoint is a UX optimisation. The authoritative uniqueness rule remains
 * `Rule::unique(...)` in StoreUserRequest / UpdateUserRequest; this request only
 * bounds the query to two allowlisted columns.
 */
final class CheckUserAvailabilityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(PermissionName::ViewAnyUser->value) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'field' => ['required', 'string', 'in:username,email'],
            'value' => ['required', 'string', 'max:255'],
            'ignore_user_id' => ['nullable', 'uuid'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $field = $this->input('field');
        $value = $this->input('value');
        $ignore = $this->input('ignore_user_id');

        $this->merge([
            'field' => is_string($field) ? Str::lower(trim($field)) : $field,
            'value' => is_string($value) ? Str::lower(trim($value)) : $value,
            'ignore_user_id' => $ignore === '' ? null : $ignore,
        ]);
    }
}
