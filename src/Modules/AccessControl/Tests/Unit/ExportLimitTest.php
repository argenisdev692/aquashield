<?php

declare(strict_types=1);

use Shared\Support\ExportLimit;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

uses(TestCase::class);

it('caps PDF lower than the streamed tabular formats', function (): void {
    expect(ExportLimit::for('pdf'))->toBe(2_000)
        ->and(ExportLimit::for('csv'))->toBe(10_000)
        ->and(ExportLimit::for('xlsx'))->toBe(10_000);
});

it('returns the ceiling at the boundary and rejects one row above it', function (): void {
    expect(ExportLimit::ensureWithin(10_000, 'csv'))->toBe(10_000)
        ->and(ExportLimit::ensureWithin(2_000, 'pdf'))->toBe(2_000);

    expect(fn () => ExportLimit::ensureWithin(10_001, 'csv'))
        ->toThrow(HttpException::class, 'The csv export is limited to 10000 rows.');

    expect(fn () => ExportLimit::ensureWithin(2_001, 'pdf'))
        ->toThrow(HttpException::class, 'The pdf export is limited to 2000 rows.');
});
