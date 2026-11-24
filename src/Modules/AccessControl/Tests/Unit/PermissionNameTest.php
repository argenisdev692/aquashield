<?php

declare(strict_types=1);

use Modules\AccessControl\Support\PermissionName;
use Tests\TestCase;

uses(TestCase::class);

it('derives the module group from the action prefix', function (): void {
    expect(PermissionName::group('VIEW_ANY_USER'))->toBe('USER')
        ->and(PermissionName::group('BULK_DELETE_USER'))->toBe('USER')
        ->and(PermissionName::group('ASSIGN_ROLES_USER'))->toBe('USER')
        ->and(PermissionName::group('VIEW_ANY_AUTH_SESSIONS'))->toBe('AUTH_SESSIONS')
        ->and(PermissionName::group('BULK_DELETE_AUTH_SESSIONS'))->toBe('AUTH_SESSIONS')
        ->and(PermissionName::group('VIEW_COMPANY_DATA'))->toBe('COMPANY_DATA')
        ->and(PermissionName::group('APPROVE_CLAIM'))->toBe('APPROVE_CLAIM');
});

it('normalises a name to uppercase and trims it', function (): void {
    expect(PermissionName::normalise('  approve_claim '))->toBe('APPROVE_CLAIM');
});

it('accepts the seeded identifier shape and rejects lower-case or punctuation', function (): void {
    expect(preg_match(PermissionName::RULE, 'VIEW_ANY_USER'))->toBe(1)
        ->and(preg_match(PermissionName::RULE, 'view_any_user'))->toBe(0)
        ->and(preg_match(PermissionName::RULE, 'VIEW-ANY-USER'))->toBe(0)
        ->and(preg_match(PermissionName::RULE, '1VIEW'))->toBe(0);
});
