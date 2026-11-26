<?php

declare(strict_types=1);

namespace Modules\Backups\Infrastructure\Support;

use RuntimeException;

/**
 * The one rule about archive encryption: production must have a password.
 *
 * spatie/laravel-backup treats an empty `backup.backup.password` as
 * "encryption disabled" and writes a plain-text database dump. A missing env
 * key is an operator mistake, not a supported deployment, so it fails at boot
 * instead of silently exposing every table on the backup disk.
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
    public static function assertConfiguredInProduction(): void
    {
        if (app()->isProduction() && ! self::isConfigured()) {
            throw new RuntimeException(
                'BACKUP_ARCHIVE_PASSWORD must be set in production: '
                .'database dumps would otherwise be stored unencrypted.',
            );
        }
    }
}
