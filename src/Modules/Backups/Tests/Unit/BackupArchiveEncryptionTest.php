<?php

declare(strict_types=1);

use Modules\Backups\Infrastructure\Support\BackupArchiveEncryption;
use Tests\TestCase;

uses(TestCase::class);

it('treats a missing or blank password as unconfigured', function (): void {
    config(['backup.backup.password' => null]);
    expect(BackupArchiveEncryption::isConfigured())->toBeFalse();

    config(['backup.backup.password' => '   ']);
    expect(BackupArchiveEncryption::isConfigured())->toBeFalse();

    config(['backup.backup.password' => 'secret']);
    expect(BackupArchiveEncryption::isConfigured())->toBeTrue();
});

it('refuses production without an archive password', function (): void {
    config(['backup.backup.password' => null]);
    $this->app['env'] = 'production';

    expect(fn () => BackupArchiveEncryption::assertConfigured())
        ->toThrow(RuntimeException::class, 'BACKUP_ARCHIVE_PASSWORD');
});

it('allows production when the archive password is configured', function (): void {
    config(['backup.backup.password' => 'secret']);
    $this->app['env'] = 'production';

    BackupArchiveEncryption::assertConfigured();

    expect(true)->toBeTrue();
});

it('refuses staging without an archive password', function (): void {
    config(['backup.backup.password' => null]);
    $this->app['env'] = 'staging';

    expect(fn () => BackupArchiveEncryption::assertConfigured())
        ->toThrow(RuntimeException::class, 'BACKUP_ARCHIVE_PASSWORD');
});

it('allows local without an archive password', function (): void {
    config(['backup.backup.password' => null]);
    $this->app['env'] = 'local';

    BackupArchiveEncryption::assertConfigured();

    expect(true)->toBeTrue();
});
