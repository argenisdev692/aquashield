<?php

declare(strict_types=1);

namespace Shared\Support;

/**
 * One definition of the audit-property projection.
 *
 * The ActivityLog panel and the Backups activity reader must expose the same
 * safe fields for the same event; a second copy of this list is a security
 * rule duplicated, not a style choice. Unknown events expose nothing (fail
 * closed), and PII/secret keys are absent from every arm.
 */
final class ActivityProperties
{
    /** @var list<string> */
    private const array BACKUP_KEYS = ['disk', 'filename', 'size_bytes', 'reason'];

    /**
     * @param array<string, mixed> $properties
     * @return array<string, string|int|list<string>>
     */
    public static function forEvent(string $event, array $properties): array
    {
        $allowed = match (true) {
            $event === 'role.updated' => ['name', 'previous_name', 'permissions_added', 'permissions_removed'],
            $event === 'role.created', $event === 'role.deleted' => ['name', 'permissions_count'],
            str_starts_with($event, 'permission.') => ['name', 'previous_name'],
            $event === 'user.created' => ['roles', 'permissions', 'onboarding'],
            $event === 'user.roles.assigned' => ['roles'],
            $event === 'user.permissions.assigned' => ['permissions'],
            $event === 'user.updated' => ['changed'],
            $event === 'user.status.changed' => ['from', 'to'],
            $event === 'user.exported', $event === 'activity.exported' => ['format', 'rows'],
            $event === 'activity.viewed' => ['entry'],
            $event === 'financial-fields-updated' => ['changed'],
            str_starts_with($event, 'backup.') => self::BACKUP_KEYS,
            str_starts_with($event, 'auth.') => ['guard', 'passkey'],
            default => [],
        };

        return self::project($allowed, $properties);
    }

    /**
     * The Backups read model accepts only scalars; it shares the same key list
     * but never needs a list.
     *
     * @param array<string, mixed> $properties
     * @return array<string, string|int>
     */
    public static function forBackupEvent(array $properties): array
    {
        $safe = [];

        foreach (self::project(self::BACKUP_KEYS, $properties) as $key => $value) {
            if (is_string($value) || is_int($value)) {
                $safe[$key] = $value;
            }
        }

        return $safe;
    }

    /**
     * @param list<string> $allowed
     * @param array<string, mixed> $properties
     * @return array<string, string|int|list<string>>
     */
    private static function project(array $allowed, array $properties): array
    {
        /** @var array<string, string|int|list<string>> $safe */
        $safe = [];

        foreach ($allowed as $key) {
            $value = $properties[$key] ?? null;

            if (is_string($value) || is_int($value)) {
                $safe[$key] = $value;

                continue;
            }

            if (! is_array($value)) {
                continue;
            }

            $list = [];

            foreach ($value as $item) {
                if (is_string($item)) {
                    $list[] = $item;
                }
            }

            if ($list !== []) {
                $safe[$key] = $list;
            }
        }

        return $safe;
    }
}
