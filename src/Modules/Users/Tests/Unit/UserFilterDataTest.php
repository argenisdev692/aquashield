<?php

declare(strict_types=1);

use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Modules\Users\Data\UserFilterData;
use Modules\Users\Tests\Support\UserTestFixtures;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    UserTestFixtures::seedPermissions();
    UserTestFixtures::role('MANAGER', []);
});

/**
 * The DataTable query contract is the seam between the React table and the
 * export. These assertions pin the mapping once; the suite only needs the
 * permission rows, not application data.
 */
it('maps the table query contract onto the filter DTO', function (): void {
    $request = Request::create('/users', 'GET', [
        'search' => 'ada',
        'filter' => [
            'status' => 'trashed',
            'account_status' => 'invited',
            'role' => ['MANAGER', 'SUPER_ADMIN'],
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
        ->and($filters->accountStatus)->toBe('invited')
        ->and($filters->role)->toBe(['MANAGER', 'SUPER_ADMIN'])
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

it('rejects a scalar filter instead of silently ignoring it', function (): void {
    UserFilterData::fromRequest(Request::create('/users', 'GET', [
        'filter' => 'status',
    ]));
})->throws(ValidationException::class);

it('rejects an account status outside the enum', function (): void {
    UserFilterData::fromRequest(Request::create('/users', 'GET', [
        'filter' => ['account_status' => 'deleted'],
    ]));
})->throws(ValidationException::class);

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

it('normalises a legacy scalar role filter to a list', function (): void {
    $filters = UserFilterData::fromRequest(Request::create('/users', 'GET', [
        'filter' => ['role' => 'MANAGER'],
    ]));

    expect($filters->role)->toBe(['MANAGER']);
});

it('allows name and account status as sort columns', function (): void {
    $filters = UserFilterData::fromRequest(Request::create('/users', 'GET', [
        'sort' => 'name',
    ]));

    expect($filters->sortField)->toBe('name');

    $filters = UserFilterData::fromRequest(Request::create('/users', 'GET', [
        'sort' => '-status',
    ]));

    expect($filters->sortField)->toBe('status')
        ->and($filters->sortOrder)->toBe(-1);
});

it('rejects a page size above the export-safe maximum', function (): void {
    UserFilterData::fromRequest(Request::create('/users', 'GET', [
        'per_page' => '101',
    ]));
})->throws(ValidationException::class);

it('rejects more than fifty role filters', function (): void {
    for ($i = 1; $i <= 51; $i++) {
        Role::findOrCreate('ROLE_'.$i, 'web');
    }

    UserFilterData::fromRequest(Request::create('/users', 'GET', [
        'filter' => ['role' => Role::query()->pluck('name')->all()],
    ]));
})->throws(ValidationException::class);

it('rejects a date filter that is not Y-m-d', function (): void {
    UserFilterData::fromRequest(Request::create('/users', 'GET', [
        'filter' => ['date_from' => '01/31/2026'],
    ]));
})->throws(ValidationException::class);
