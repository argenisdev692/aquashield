<?php

declare(strict_types=1);

namespace Modules\Users\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

final class AssignPermissionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        $target = $this->route('user');

        return $target instanceof User
            && ($this->user()?->can('assignPermissions', $target) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // Bounded: each item runs an `exists` query (OWASP API4). The
            // permission registry seeds 505 names today; 600 leaves headroom.
            'permissions' => ['present', 'array', 'max:600'],
            'permissions.*' => ['string', 'distinct', 'exists:permissions,name'],
        ];
    }
}
