<?php

declare(strict_types=1);

use Shared\Support\SpreadsheetCell;
use Tests\TestCase;

uses(TestCase::class);

it('neutralises every spreadsheet formula prefix', function (string $value): void {
    expect(SpreadsheetCell::sanitise($value))->toBe("'".$value);
})->with([
    '=SUM(A1:A2)',
    '+1',
    '-1',
    '@cmd',
    "\tvalue",
    "\rvalue",
]);

it('normalises a date, a bool and null', function (): void {
    expect(SpreadsheetCell::sanitise(new DateTimeImmutable('2026-11-24 10:30:00')))->toBe('2026-11-24 10:30')
        ->and(SpreadsheetCell::sanitise(true))->toBe(1)
        ->and(SpreadsheetCell::sanitise(false))->toBe(0)
        ->and(SpreadsheetCell::sanitise(null))->toBe('');
});

it('leaves ordinary text and numbers untouched', function (): void {
    expect(SpreadsheetCell::sanitise('Ada Lovelace'))->toBe('Ada Lovelace')
        ->and(SpreadsheetCell::sanitise(42))->toBe(42)
        ->and(SpreadsheetCell::sanitise(1.5))->toBe(1.5);
});
