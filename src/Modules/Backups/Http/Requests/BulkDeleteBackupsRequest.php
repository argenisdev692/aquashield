<?php

declare(strict_types=1);

namespace Modules\Backups\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Shared\Application\DTOs\BulkUuidsData;

final class BulkDeleteBackupsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('BULK_DELETE_BACKUPS') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return BulkUuidsData::rules();
    }
}
