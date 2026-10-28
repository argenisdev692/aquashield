<?php

declare(strict_types=1);

namespace Modules\Users\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Shared\Application\DTOs\BulkUuidsData;

final class BulkDeleteUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('BULK_DELETE_USER') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return BulkUuidsData::rules();
    }
}
