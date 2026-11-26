<?php

declare(strict_types=1);

namespace Modules\Backups\Infrastructure\Support;

use RuntimeException;

/**
 * The one rule about archive encryption: every shared environment must have a
 * password.
 *
 * spatie/laravel-backup treats an empty `backup.backup.password` as
 * "encryption disabled" and writes a plain-text database dump. A missing env
 * key is an operator mistake, not a supported deployment, so it fails at boot
 * instead of silently exposing every table on the backup disk. `local` and
 * `testing` are exempt because their dump holds no customer data.
 */
final class BackupArchiveEncryption
{
    public static function isConfigured(): bool
    {
        return trim((string) config('backup.backup.password', '')) !== '';
    }

    /**
     * @throws RuntimeException
     */
    public static function assertConfigured(): void
    {
        if (! app()->environment(['local', 'testing']) && ! self::isConfigured()) {
            throw new RuntimeException(
                'BACKUP_ARCHIVE_PASSWORD must be set outside local/testing: '
                .'database dumps would otherwise be stored unencrypted.',
            );
        }
    }
}
