<?php

declare(strict_types=1);

namespace Modules\AccessControl\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\AccessControl\Data\RoleData;
use Modules\AccessControl\Support\PermissionName;
use Shared\Application\Rules\ExistingPermissionNames;
use Shared\Domain\Enums\Permission as PermissionEnum;

final class StoreRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(PermissionEnum::CreateRole->value) ?? false;
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
                Rule::notIn(RoleData::systemNames()),
                Rule::unique('roles', 'name')->where('guard_name', 'web'),
            ],
            // One `whereIn` for the whole array, not one `exists` per item (OWASP API4).
            // The array cap is a resource bound; the catalogue currently holds ~507 names.
            'permissions' => ['nullable', 'array', 'max:1000', new ExistingPermissionNames],
            'permissions.*' => ['string', 'max:64', 'distinct'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('name'))) {
            $this->merge(['name' => PermissionName::normalise($this->input('name'))]);
        }

        if ($this->input('permissions') === null) {
            $this->merge(['permissions' => []]);
        }
    }
}
