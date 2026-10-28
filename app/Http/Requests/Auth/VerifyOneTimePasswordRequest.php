<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

final class VerifyOneTimePasswordRequest extends FormRequest
{
    /**
     * The email is intentionally NOT an input here. The send step stores it in
     * the session, so a visitor cannot request a code for one address and then
     * try it against another. The code is the only transport value.
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
            'code' => ['required', 'string', 'digits:6'],
        ];
    }
}
