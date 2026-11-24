<?php

declare(strict_types=1);

namespace Modules\AccessControl\Http\Requests;

use App\Models\Role;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\AccessControl\Data\RoleData;
use Modules\AccessControl\Rules\ExistingPermissionNames;
use Modules\AccessControl\Support\PermissionName;
use Shared\Domain\Enums\Permission as PermissionEnum;

final class UpdateRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(PermissionEnum::UpdateRole->value) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $role = $this->route('role');
        $roleId = $role instanceof Role ? $role->getKey() : null;
        // The current name stays legal (a system role's permission set is editable);
        // any *other* system name is reserved.
        $reserved = array_values(array_diff(
            RoleData::systemNames(),
            $role instanceof Role ? [(string) $role->name] : [],
        ));

        return [
            'name' => [
                'required',
                'string',
                'max:64',
                'regex:'.PermissionName::RULE,
                Rule::notIn($reserved),
                Rule::unique('roles', 'name')->where('guard_name', 'web')->ignore($roleId),
            ],
            /*
             * Required: the edit form sends the whole matrix. A partial request
             * must not silently revoke every permission.
             */
            'permissions' => ['required', 'array', 'max:600', new ExistingPermissionNames],
            'permissions.*' => ['string', 'distinct'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('name'))) {
            $this->merge(['name' => PermissionName::normalise($this->input('name'))]);
        }
    }
}
