<?php

declare(strict_types=1);

use Shared\Support\ExportLimit;
use Tests\TestCase;

uses(TestCase::class);

it('caps PDF lower than the streamed tabular formats', function (): void {
    expect(ExportLimit::for('pdf'))->toBe(2_000)
        ->and(ExportLimit::for('csv'))->toBe(10_000)
        ->and(ExportLimit::for('xlsx'))->toBe(10_000);
});
