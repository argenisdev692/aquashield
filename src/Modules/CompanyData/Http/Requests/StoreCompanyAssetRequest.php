<?php

declare(strict_types=1);

namespace Modules\CompanyData\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

final class StoreCompanyAssetRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof User
            && $user->getAllPermissions()->contains('name', 'UPDATE_COMPANY_DATA');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // SVG is deliberately absent: an SVG can carry script and these
            // assets are rendered on the public site and inside email. The
            // dimensions floor rejects the 1×1 tracking pixel; the ceiling
            // bounds the decode memory before GD ever runs.
            'file' => [
                'required',
                'file',
                'image',
                'mimes:png,jpg,jpeg,webp',
                'max:2048',
                'dimensions:min_width=16,min_height=16,max_width=4096,max_height=4096',
            ],
        ];
    }
}
