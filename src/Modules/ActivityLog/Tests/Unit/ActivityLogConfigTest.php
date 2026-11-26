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

it('refuses to boot in production with the audit trail disabled', function (): void {
    config()->set('activitylog.enabled', false);

    expect(fn () => ActivityLogServiceProvider::assertValidConfiguration(true))
        ->toThrow(RuntimeException::class);
});

it('allows the audit switch to be off outside production', function (): void {
    config()->set('activitylog.enabled', false);

    ActivityLogServiceProvider::assertValidConfiguration(false);

    expect(true)->toBeTrue();
});
