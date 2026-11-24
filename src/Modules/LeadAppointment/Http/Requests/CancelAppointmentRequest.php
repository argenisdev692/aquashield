<?php

declare(strict_types=1);

namespace Modules\LeadAppointment\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class CancelAppointmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'max:500'],
        ];
    }
}
