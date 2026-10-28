<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Modules\Users\Data\UserFilterData;
use Tests\TestCase;

uses(TestCase::class);

/**
 * The DataTable query contract is the seam between the Vue table and the
 * export. These assertions pin the mapping once, without a database.
 */
it('maps the table query contract onto the filter DTO', function (): void {
    $request = Request::create('/users', 'GET', [
        'search' => 'ada',
        'filter' => [
            'status' => 'trashed',
            'date_from' => '2026-01-01',
            'date_to' => '2026-01-31',
        ],
        'sort' => '-email',
        'page' => '2',
        'per_page' => '50',
    ]);

    $filters = UserFilterData::fromRequest($request);

    expect($filters->search)->toBe('ada')
        ->and($filters->status)->toBe('trashed')
        ->and($filters->dateFrom)->toBe('2026-01-01')
        ->and($filters->dateTo)->toBe('2026-01-31')
        ->and($filters->sortField)->toBe('email')
        ->and($filters->sortOrder)->toBe(-1)
        ->and($filters->page)->toBe(2)
        ->and($filters->perPage)->toBe(50);
});

it('falls back to the newest-first first page', function (): void {
    $filters = UserFilterData::fromRequest(Request::create('/users', 'GET'));

    expect($filters->sortField)->toBe('created_at')
        ->and($filters->sortOrder)->toBe(-1)
        ->and($filters->page)->toBe(1)
        ->and($filters->perPage)->toBe(15)
        ->and($filters->status)->toBeNull();
});

it('rejects an unknown filter key instead of silently ignoring it', function (): void {
    UserFilterData::fromRequest(Request::create('/users', 'GET', [
        'filter' => ['password' => 'x'],
    ]));
})->throws(ValidationException::class);

it('rejects a sort field outside the column allowlist', function (): void {
    UserFilterData::fromRequest(Request::create('/users', 'GET', [
        'sort' => 'password',
    ]));
})->throws(ValidationException::class);

it('rejects a page size above the export-safe maximum', function (): void {
    UserFilterData::fromRequest(Request::create('/users', 'GET', [
        'per_page' => '101',
    ]));
})->throws(ValidationException::class);
