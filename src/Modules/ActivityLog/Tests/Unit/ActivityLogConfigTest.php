<?php

declare(strict_types=1);

use Modules\ActivityLog\Providers\ActivityLogServiceProvider;
use Tests\TestCase;

uses(TestCase::class);

it('rejects a retention window shorter than one year', function (): void {
    config()->set('activitylog.clean_after_days', 0);

    expect(fn () => ActivityLogServiceProvider::assertValidConfiguration())
        ->toThrow(RuntimeException::class);
});

it('rejects the buffer while the uuid column is required', function (): void {
    config()->set('activitylog.buffer.enabled', true);

    expect(fn () => ActivityLogServiceProvider::assertValidConfiguration())
        ->toThrow(RuntimeException::class);
});
