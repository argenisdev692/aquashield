<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Modules\Backups\Data\BackupFilterData;
use Tests\TestCase;

uses(TestCase::class);

it('defaults to the active inventory, newest first', function (): void {
    $filters = BackupFilterData::fromRequest(Request::create('/backups', 'GET'));

    expect($filters->status)->toBe('active')
        ->and($filters->sortField)->toBe('taken_at')
        ->and($filters->sortOrder)->toBe(-1)
        ->and($filters->perPage)->toBe(15);
});

it('accepts a start-only date window', function (): void {
    $filters = BackupFilterData::fromRequest(Request::create('/backups', 'GET', [
        'filter' => ['date_from' => '2026-01-01'],
    ]));

    expect($filters->dateFrom)->toBe('2026-01-01')
        ->and($filters->dateTo)->toBeNull();
});

it('rejects an inverted date window instead of returning an empty page', function (): void {
    BackupFilterData::fromRequest(Request::create('/backups', 'GET', [
        'filter' => ['date_from' => '2026-02-01', 'date_to' => '2026-01-01'],
    ]));
})->throws(ValidationException::class);

it('rejects a date that is not Y-m-d', function (): void {
    BackupFilterData::fromRequest(Request::create('/backups', 'GET', [
        'filter' => ['date_to' => '31/01/2026'],
    ]));
})->throws(ValidationException::class);

it('rejects a scalar filter instead of silently widening the list', function (): void {
    BackupFilterData::fromRequest(Request::create('/backups', 'GET', [
        'filter' => 'trashed',
    ]));
})->throws(ValidationException::class);
