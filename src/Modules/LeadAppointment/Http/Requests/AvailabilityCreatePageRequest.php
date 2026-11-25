<?php

declare(strict_types=1);

namespace Modules\LeadAppointment\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Shared\Domain\Enums\Permission;

final class AvailabilityCreatePageRequest extends FormRequest
{
    /**
     * This is the read behind the month grid's day link, which both an editor
     * and a creator use. The controller decides which branch each capability
     * gets; the write stays on `CreateAvailabilityException`.
     */
    public function authorize(): bool
    {
        return $this->user()?->canAny([
            Permission::CreateAvailabilityException->value,
            Permission::UpdateAvailabilityException->value,
        ]) ?? false;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'date' => ['nullable', 'date_format:Y-m-d'],
        ];
    }
}
