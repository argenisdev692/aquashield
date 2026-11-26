<?php

declare(strict_types=1);

use Shared\Support\ActivityProperties;

it('projects role changes and keeps the permission lists', function (): void {
    $safe = ActivityProperties::forEvent('role.updated', [
        'name' => 'Manager',
        'previous_name' => 'Supervisor',
        'permissions_added' => ['VIEW_USERS'],
        'permissions_removed' => ['EXPORT_USERS'],
        'secret' => 'nope',
    ]);

    expect($safe)->toBe([
        'name' => 'Manager',
        'previous_name' => 'Supervisor',
        'permissions_added' => ['VIEW_USERS'],
        'permissions_removed' => ['EXPORT_USERS'],
    ]);
});

it('exposes no PII for auth events', function (): void {
    $safe = ActivityProperties::forEvent('auth.failed', [
        'guard' => 'web',
        'email' => 'must-not-leak@example.test',
        'ip_address' => '127.0.0.1',
        'user_agent' => 'test-agent',
    ]);

    expect($safe)->toBe(['guard' => 'web']);
});

it('exposes nothing for an unknown event', function (): void {
    expect(ActivityProperties::forEvent('some.unknown', ['x' => 'y']))->toBe([]);
});

it('shares the backup key list but keeps the Backups read model scalar-only', function (): void {
    $properties = [
        'disk' => 'local',
        'filename' => 'archive.zip',
        'size_bytes' => 10,
        'reason' => 'operator',
        'changed' => ['not-a-backup-key'],
    ];

    expect(ActivityProperties::forBackupEvent($properties))->toBe([
        'disk' => 'local',
        'filename' => 'archive.zip',
        'size_bytes' => 10,
        'reason' => 'operator',
    ]);
});
