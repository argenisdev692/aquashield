<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Modules\AccessControl\Data\RoleFilterData;
use Tests\TestCase;

uses(TestCase::class);

it('parses the role list contract', function (): void {
    $request = Request::create('/roles', 'GET', [
        'filter' => ['type' => 'custom'],
        'sort' => '-permissions_count',
        'page' => '2',
        'per_page' => '25',
    ]);

    $filters = RoleFilterData::fromRequest($request);

    expect($filters->type)->toBe('custom')
        ->and($filters->sortField)->toBe('permissions_count')
        ->and($filters->sortOrder)->toBe(-1)
        ->and($filters->page)->toBe(2)
        ->and($filters->perPage)->toBe(25);
});

it('defaults to name ascending', function (): void {
    $filters = RoleFilterData::fromRequest(Request::create('/roles', 'GET'));

    expect($filters->sortField)->toBe('name')
        ->and($filters->sortOrder)->toBe(1)
        ->and($filters->perPage)->toBe(15);
});

it('rejects an unknown filter key instead of widening the list', function (): void {
    $request = Request::create('/roles', 'GET', ['filter' => ['password' => 'x']]);

    expect(fn () => RoleFilterData::fromRequest($request))
        ->toThrow(ValidationException::class);
});

it('rejects a sort field outside the allowlist', function (): void {
    $request = Request::create('/roles', 'GET', ['sort' => 'password_hash']);

    expect(fn () => RoleFilterData::fromRequest($request))
        ->toThrow(ValidationException::class);
});

it('rejects an invalid type and an inverted date range', function (): void {
    expect(fn () => RoleFilterData::fromRequest(Request::create('/roles', 'GET', [
        'filter' => ['type' => 'other'],
    ])))->toThrow(ValidationException::class);

    expect(fn () => RoleFilterData::fromRequest(Request::create('/roles', 'GET', [
        'filter' => ['date_from' => '2026-02-01', 'date_to' => '2026-01-01'],
    ])))->toThrow(ValidationException::class);
});

it('caps the page size at 100', function (): void {
    expect(fn () => RoleFilterData::fromRequest(Request::create('/roles', 'GET', [
        'per_page' => '101',
    ])))->toThrow(ValidationException::class);
});
