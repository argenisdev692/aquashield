<?php

declare(strict_types=1);

namespace App\Support;

/**
 * The saved-view allowlist.
 *
 * A view is data a user can write, so the server — not the browser — decides
 * which screens exist and which filter keys each screen accepts. An unknown
 * key is rejected instead of stored: a saved view is later replayed into a
 * list URL, and an unfiltered key could silently widen that request.
 */
final class SavedViewCatalog
{
    public const int MAX_VIEWS_PER_RESOURCE = 20;

    public const int MAX_NAME_LENGTH = 60;

    /**
     * @var array<string, list<string>>
     */
    private const array FILTERS = [
        'users' => ['status', 'role', 'permission', 'date_from', 'date_to'],
        'leads' => ['status', 'record_state', 'source', 'region', 'owner_uuid', 'emergency', 'spam', 'date_from', 'date_to'],
        'appointments' => ['status', 'record_state', 'kind', 'region', 'date_from', 'date_to'],
        'backups' => ['status', 'disk', 'date_from', 'date_to'],
        'activity' => ['log_name', 'event', 'date_from', 'date_to'],
    ];

    /**
     * @return list<string>
     */
    public static function resources(): array
    {
        return array_keys(self::FILTERS);
    }

    public static function supports(string $resource): bool
    {
        return array_key_exists($resource, self::FILTERS);
    }

    /**
     * @return list<string>
     */
    public static function filterKeys(string $resource): array
    {
        return self::FILTERS[$resource] ?? [];
    }
}
