<?php

declare(strict_types=1);

namespace Shared\Application\Rules;

use App\Models\Role;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * The role twin of {@see ExistingPermissionNames}, for the same reason.
 *
 * `exists:roles,name` on `roles.*` runs one query per selected role. The
 * access forms legitimately submit the whole selected set, so the batched
 * `whereIn` is the normal path, not an optimisation for an edge case.
 */
final readonly class ExistingRoleNames implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_array($value)) {
            return;
        }

        /** @var list<string> $names */
        $names = array_values(array_filter(
            $value,
            static fn (mixed $name): bool => is_string($name),
        ));

        if ($names === []) {
            return;
        }

        /** @var list<string> $existing */
        $existing = Role::query()
            ->where('guard_name', 'web')
            ->whereIn('name', $names)
            ->pluck('name')
            ->map(static fn (mixed $name): string => (string) $name)
            ->values()
            ->all();

        $missing = array_values(array_diff($names, $existing));

        if ($missing !== []) {
            $fail('Unknown role(s): '.implode(', ', $missing).'.');
        }
    }
}
