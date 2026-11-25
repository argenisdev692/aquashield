<?php

declare(strict_types=1);

namespace Shared\Support;

/**
 * A post-write return path that can only point inside one module.
 *
 * A create/edit page is reached from a filtered index; after the write the
 * operator should land back on that view instead of the bare list. The value
 * travels through the form, so it must never become an open redirect: only a
 * same-origin path under one of the caller's allowed prefixes is accepted.
 */
final class ReturnPath
{
    private const int MAX_LENGTH = 2048;

    /**
     * @param list<string> $allowedPrefixes e.g. ['/roles', '/permissions']
     */
    public static function sanitize(mixed $value, array $allowedPrefixes): ?string
    {
        if (! is_string($value) || $value === '' || strlen($value) > self::MAX_LENGTH) {
            return null;
        }

        // A leading `//` is protocol-relative, and a backslash is normalised
        // to `/` by browsers on some platforms. Neither may reach `Location`.
        if (! str_starts_with($value, '/')
            || str_starts_with($value, '//')
            || str_contains($value, '\\')) {
            return null;
        }

        $parts = parse_url($value);

        if ($parts === false || isset($parts['scheme']) || isset($parts['host'])
            || isset($parts['user']) || isset($parts['port'])) {
            return null;
        }

        $path = $parts['path'] ?? null;

        if ($path === null || $path === '' || $path[0] !== '/' || str_contains($path, '..')) {
            return null;
        }

        foreach ($allowedPrefixes as $prefix) {
            if ($path === $prefix || str_starts_with($path, $prefix.'/')) {
                return $value;
            }
        }

        return null;
    }
}
