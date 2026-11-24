<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Modules\ActivityLog\Data\ActivityFilterData;
use Tests\TestCase;

uses(TestCase::class);

it('defaults to the newest-first first page', function (): void {
    $filters = ActivityFilterData::fromRequest(Request::create('/activity', 'GET'));

    expect($filters->sortField)->toBe('created_at')
        ->and($filters->sortOrder)->toBe(-1)
        ->and($filters->page)->toBe(1)
        ->and($filters->perPage)->toBe(15);
});

it('accepts a start-only date window', function (): void {
    $filters = ActivityFilterData::fromRequest(Request::create('/activity', 'GET', [
        'filter' => ['date_from' => '2026-01-01'],
    ]));

    expect($filters->dateFrom)->toBe('2026-01-01')
        ->and($filters->dateTo)->toBeNull();
});

it('rejects an inverted date window instead of returning an empty page', function (): void {
    ActivityFilterData::fromRequest(Request::create('/activity', 'GET', [
        'filter' => ['date_from' => '2026-02-01', 'date_to' => '2026-01-01'],
    ]));
})->throws(ValidationException::class);

it('rejects a date that is not Y-m-d', function (): void {
    ActivityFilterData::fromRequest(Request::create('/activity', 'GET', [
        'filter' => ['date_from' => '01/31/2026'],
    ]));
})->throws(ValidationException::class);
