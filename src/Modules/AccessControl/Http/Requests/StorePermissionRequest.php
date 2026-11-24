<?php

declare(strict_types=1);

namespace Modules\AccessControl\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\AccessControl\Support\PermissionName;
use Shared\Domain\Enums\Permission as PermissionEnum;

final class StorePermissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(PermissionEnum::CreatePermission->value) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:64',
                'regex:'.PermissionName::RULE,
                Rule::notIn(PermissionEnum::userValues()),
                Rule::unique('permissions', 'name')->where('guard_name', 'web'),
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('name'))) {
            $this->merge(['name' => PermissionName::normalise($this->input('name'))]);
        }
    }
}
