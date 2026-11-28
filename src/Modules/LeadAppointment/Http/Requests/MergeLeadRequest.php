<?php

declare(strict_types=1);

namespace Modules\LeadAppointment\Http\Requests;

use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Shared\Domain\Enums\Permission;

final class MergeLeadRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user->can(Permission::UpdateLead->value) === true
            && $user->can(Permission::ViewLead->value) === true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'duplicate_uuid' => [
                'required',
                'uuid',
                // The action loads non-trashed leads only; a trashed target is a
                // 422 here instead of a confusing 404 from LeadNotFoundException.
                Rule::exists('leads', 'uuid')->whereNull('deleted_at'),
                function (string $attribute, mixed $value, Closure $fail): void {
                    if ($value === (string) $this->route('uuid')) {
                        $fail('A lead cannot be merged into itself.');
                    }
                },
            ],
        ];
    }
}
