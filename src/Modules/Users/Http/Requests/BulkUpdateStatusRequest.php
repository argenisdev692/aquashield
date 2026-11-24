<?php

declare(strict_types=1);

namespace Modules\Users\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Users\Enums\UserStatus;
use Shared\Application\DTOs\BulkUuidsData;
use Shared\Domain\Enums\Permission as PermissionName;

final class BulkUpdateStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(PermissionName::UpdateUser->value) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...BulkUuidsData::rules(),
            // Invited is set only by the onboarding flow; the action repeats
            // this rule, and both derive from the one enum definition.
            'status' => [
                'required',
                Rule::enum(UserStatus::class)->only([UserStatus::Active, UserStatus::Suspended]),
            ],
        ];
    }
}
