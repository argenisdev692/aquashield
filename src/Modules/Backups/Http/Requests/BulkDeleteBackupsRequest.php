<?php

declare(strict_types=1);

namespace Modules\Backups\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Shared\Application\DTOs\BulkUuidsData;
use Shared\Domain\Enums\Permission as PermissionName;

final class BulkDeleteBackupsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(PermissionName::BulkDeleteBackup->value) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return BulkUuidsData::rules();
    }
}
