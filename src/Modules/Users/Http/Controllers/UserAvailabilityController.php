<?php

declare(strict_types=1);

namespace Modules\Users\Http\Controllers;

use App\Rules\Username;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Modules\Users\Data\UserAvailabilityData;
use Modules\Users\Http\Requests\CheckUserAvailabilityRequest;
use Shared\Application\Queries\CheckUserFieldAvailability;

/**
 * The throttled "is this username/e-mail free?" probe used by the create and
 * edit forms.
 *
 * This is a UX optimisation, never the authority: StoreUserRequest and
 * UpdateUserRequest still carry `Rule::unique(...)` with the correct ignore.
 * The endpoint only allows the two allowlisted columns and normalises the value
 * before querying, so it cannot be turned into a generic user-table oracle.
 */
final readonly class UserAvailabilityController
{
    public function __construct(private CheckUserFieldAvailability $check) {}

    public function __invoke(CheckUserAvailabilityRequest $request): JsonResponse
    {
        /** @var array{field: string, value: string, ignore_user_id?: string|null} $data */
        $data = $request->validated();

        $field = $data['field'];
        $value = $data['value'];
        $ignore = $data['ignore_user_id'] ?? null;

        if ($field === 'email' && filter_var($value, FILTER_VALIDATE_EMAIL) === false) {
            return $this->unavailable('Enter a valid email address.');
        }

        if ($field === 'username' && ! $this->isValidUsername($value)) {
            return $this->unavailable(
                'Choose a valid username: 3–20 lowercase letters, numbers, dots, dashes or underscores.',
            );
        }

        $available = ($this->check)($field, $value, $ignore);

        if (! $available) {
            return $this->unavailable(
                $field === 'email' ? 'Email is already in use.' : 'Username is already in use.',
            );
        }

        return response()->json(new UserAvailabilityData(
            available: true,
            message: $field === 'email' ? 'Email is available.' : 'Username is available.',
        ));
    }

    private function isValidUsername(string $value): bool
    {
        return ! Validator::make(
            ['username' => $value],
            ['username' => ['required', 'string', new Username]],
        )->fails();
    }

    private function unavailable(string $message): JsonResponse
    {
        return response()->json(new UserAvailabilityData(
            available: false,
            message: $message,
        ));
    }
}
