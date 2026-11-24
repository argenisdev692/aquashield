<?php

declare(strict_types=1);

namespace Modules\AccessControl\Support;

use Illuminate\Support\Str;

/**
 * The one definition of a permission's name and module group.
 *
 * Spatie stores a flat `name`; the admin UI groups the catalogue by module
 * (`VIEW_ANY_AUTH_SESSIONS` → `AUTH_SESSIONS`). Stripping the last underscore
 * would produce `SESSIONS`, so the known action prefixes are removed instead,
 * longest first.
 */
final class PermissionName
{
    /**
     * Public API documentation for the validation regex.
     */
    public const string PATTERN = '^[A-Z][A-Z0-9_]{2,63}$';

    /**
     * The same contract with the delimiters Laravel's `regex` rule expects.
     */
    public const string RULE = '/'.self::PATTERN.'/';

    /**
     * Longest first: `BULK_DELETE` must win over `DELETE`.
     *
     * @var list<string>
     */
    private const array ACTIONS = [
        'BULK_RESTORE',
        'BULK_DELETE',
        'ASSIGN_PERMISSIONS',
        'ASSIGN_ROLES',
        'VIEW_ANY',
        'DOWNLOAD',
        'RESTORE',
        'CREATE',
        'UPDATE',
        'DELETE',
        'EXPORT',
        'VIEW',
    ];

    public static function normalise(string $name): string
    {
        return Str::upper(trim($name));
    }

    /**
     * `VIEW_ANY_USER` → `USER`; `BULK_DELETE_AUTH_SESSIONS` → `AUTH_SESSIONS`.
     * A name without a known action prefix is its own group.
     */
    public static function group(string $name): string
    {
        foreach (self::ACTIONS as $action) {
            $prefix = $action.'_';

            if (str_starts_with($name, $prefix)) {
                $group = substr($name, strlen($prefix));

                return $group === '' ? $name : $group;
            }
        }

        return $name;
    }
}
