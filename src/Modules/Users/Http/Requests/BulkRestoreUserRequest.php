<?php

declare(strict_types=1);

namespace Modules\Users\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Shared\Application\DTOs\BulkUuidsData;
use Shared\Domain\Enums\Permission as PermissionName;

final class BulkRestoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(PermissionName::BulkRestoreUser->value) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return BulkUuidsData::rules();
    }
}
