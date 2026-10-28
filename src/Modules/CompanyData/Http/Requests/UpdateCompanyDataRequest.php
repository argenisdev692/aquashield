<?php

declare(strict_types=1);

namespace Modules\CompanyData\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

final class UpdateCompanyDataRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'company_name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:20'],
            'nif_nipc' => ['nullable', 'string', 'max:20'],
            'nie' => ['nullable', 'string', 'max:20'],
            'bank_beneficiary' => ['nullable', 'string', 'max:255'],
            'bank_iban' => ['nullable', 'string', 'max:34'],
            'bank_bic' => ['nullable', 'string', 'max:11'],
            'bank_name' => ['nullable', 'string', 'max:255'],
            'invoice_notes' => ['nullable', 'string'],
            'address' => ['nullable', 'string'],
            'address_2' => ['nullable', 'string'],
            'zip_code' => ['nullable', 'string', 'max:10'],
            'city' => ['nullable', 'string', 'max:255'],
            'state' => ['nullable', 'string', 'max:255'],
            'country' => ['nullable', 'string', 'max:255'],
            'country_code' => ['nullable', 'string', 'size:2'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'website' => ['nullable', 'url', 'max:255'],
            'facebook_link' => ['nullable', 'url', 'max:255'],
            'instagram_link' => ['nullable', 'url', 'max:255'],
            'linkedin_link' => ['nullable', 'url', 'max:255'],
            'twitter_link' => ['nullable', 'url', 'max:255'],
            'tiktok_link' => ['nullable', 'url', 'max:255'],
            // Ownership is server-side only. A client that tries to set it gets a
            // 422 rather than silently creating a row for another user.
            'user_id' => ['prohibited'],
        ];
    }
}
