<?php

declare(strict_types=1);

namespace Modules\AccessControl\Http\Requests;

use App\Models\Permission;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\AccessControl\Support\PermissionName;
use Shared\Domain\Enums\Permission as PermissionEnum;

final class UpdatePermissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(PermissionEnum::UpdatePermission->value) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $permission = $this->route('permission');
        $permissionId = $permission instanceof Permission ? $permission->getKey() : null;
        // The current name stays legal; any *other* code-referenced name is reserved.
        $reserved = array_values(array_diff(
            PermissionEnum::userValues(),
            $permission instanceof Permission ? [(string) $permission->name] : [],
        ));

        return [
            'name' => [
                'required',
                'string',
                'max:64',
                'regex:'.PermissionName::RULE,
                Rule::notIn($reserved),
                Rule::unique('permissions', 'name')
                    ->where('guard_name', 'web')
                    ->ignore($permissionId),
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
