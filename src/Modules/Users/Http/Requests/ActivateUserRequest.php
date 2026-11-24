<?php

declare(strict_types=1);

namespace Modules\Users\Http\Requests;

use App\Actions\Fortify\PasswordValidationRules;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The public activation form.
 *
 * Authorisation is the signature on the URL, checked by the controller before
 * validation runs. The password rule is the same trait Fortify uses, so an
 * activation cannot accept a credential the profile screen would reject.
 */
final class ActivateUserRequest extends FormRequest
{
    use PasswordValidationRules;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'password' => $this->passwordRules(),
        ];
    }
}
