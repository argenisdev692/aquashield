<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Modules\AccessControl\Data\PermissionFilterData;
use Tests\TestCase;

uses(TestCase::class);

it('parses the permission list contract', function (): void {
    $request = Request::create('/permissions', 'GET', [
        'filter' => ['type' => 'system', 'group' => 'AUTH_SESSIONS'],
        'sort' => '-roles_count',
        'page' => '3',
        'per_page' => '50',
    ]);

    $filters = PermissionFilterData::fromRequest($request);

    expect($filters->type)->toBe('system')
        ->and($filters->group)->toBe('AUTH_SESSIONS')
        ->and($filters->sortField)->toBe('roles_count')
        ->and($filters->sortOrder)->toBe(-1)
        ->and($filters->page)->toBe(3)
        ->and($filters->perPage)->toBe(50);
});

it('defaults to name ascending', function (): void {
    $filters = PermissionFilterData::fromRequest(Request::create('/permissions', 'GET'));

    expect($filters->sortField)->toBe('name')
        ->and($filters->sortOrder)->toBe(1)
        ->and($filters->perPage)->toBe(15);
});

it('rejects a group that is not an uppercase identifier', function (string $group): void {
    $request = Request::create('/permissions', 'GET', ['filter' => ['group' => $group]]);

    expect(fn () => PermissionFilterData::fromRequest($request))
        ->toThrow(ValidationException::class);
})->with(['auth_sessions', 'BAD-GROUP', '1ROLE']);

it('rejects an unknown filter key and an unknown sort field', function (): void {
    expect(fn () => PermissionFilterData::fromRequest(Request::create('/permissions', 'GET', [
        'filter' => ['secret' => 'x'],
    ])))->toThrow(ValidationException::class);

    expect(fn () => PermissionFilterData::fromRequest(Request::create('/permissions', 'GET', [
        'sort' => 'guard_name',
    ])))->toThrow(ValidationException::class);
});

it('rejects a non-array filter instead of returning an unfiltered list', function (): void {
    expect(fn () => PermissionFilterData::fromRequest(Request::create('/permissions', 'GET', [
        'filter' => 'type=system',
    ])))->toThrow(ValidationException::class);
});

it('ignores an empty filter parameter', function (): void {
    $filters = PermissionFilterData::fromRequest(Request::create('/permissions', 'GET', ['filter' => '']));

    expect($filters->type)->toBeNull();
});
