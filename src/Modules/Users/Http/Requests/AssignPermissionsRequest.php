<?php

declare(strict_types=1);

namespace Modules\Users\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Shared\Application\Rules\ExistingPermissionNames;

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
            // Bounded AND resolved in one `whereIn`: an `exists` per item is an
            // N-query request when the UI submits the whole selection
            // (OWASP API4). The registry seeds 505 names today; 600 leaves
            // headroom.
            'permissions' => ['present', 'array', 'max:600', new ExistingPermissionNames],
            'permissions.*' => ['string', 'distinct'],
        ];
    }
}
