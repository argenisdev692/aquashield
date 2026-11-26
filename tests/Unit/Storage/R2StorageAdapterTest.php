<?php

declare(strict_types=1);

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use Shared\Domain\Exceptions\StorageWriteException;
use Shared\Infrastructure\Storage\R2StorageAdapter;
use Tests\TestCase;

uses(TestCase::class);

it('throws when the disk reports a failed write', function (): void {
    $disk = Mockery::mock(Filesystem::class);
    $disk->shouldReceive('put')->once()->andReturnFalse();

    Storage::shouldReceive('disk')->once()->with('r2')->andReturn($disk);

    $adapter = new R2StorageAdapter('r2');

    expect(fn () => $adapter->put('company/abc/logo.webp', 'bytes'))
        ->toThrow(StorageWriteException::class);
});
