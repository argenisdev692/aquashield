<?php

declare(strict_types=1);

namespace Shared\Application\Rules;

use App\Models\Permission;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Resolves the whole permission array in ONE query.
 *
 * The per-item `exists:permissions,name` rule becomes one query per array
 * item — up to 600 round trips for a role or access save (OWASP API4). This
 * rule keeps the same contract and folds it into a single `whereIn`.
 *
 * Shared rather than module-owned because three modules validate the same
 * registry (AccessControl roles, Users direct permissions, Users create); a
 * copy per module is a second definition of "what a valid permission name is".
 */
final readonly class ExistingPermissionNames implements ValidationRule
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
        $existing = Permission::query()
            ->where('guard_name', 'web')
            ->whereIn('name', $names)
            ->pluck('name')
            ->map(static fn (mixed $name): string => (string) $name)
            ->values()
            ->all();

        $missing = array_values(array_diff($names, $existing));

        if ($missing !== []) {
            $fail('Unknown permission(s): '.implode(', ', $missing).'.');
        }
    }
}
