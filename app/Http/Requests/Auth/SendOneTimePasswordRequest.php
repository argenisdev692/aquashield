<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

final class SendOneTimePasswordRequest extends FormRequest
{
    /**
     * The endpoint is public by design: it is how a signed-out visitor starts
     * the passwordless flow. Authorisation is the rate limiter plus the
     * deliberately generic response, never the existence of the account.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email', 'max:255'],
        ];
    }
}
