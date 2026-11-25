<?php

declare(strict_types=1);

use Shared\Support\ReturnPath;
use Tests\TestCase;

uses(TestCase::class);

it('accepts an allow-listed local path with a query string', function (): void {
    $path = '/roles?search=CLAIMS&filter[type]=custom&page=2';

    expect(ReturnPath::sanitize($path, ['/roles', '/permissions']))->toBe($path);
});

it('rejects an external, protocol-relative or off-module path', function (): void {
    expect(ReturnPath::sanitize('https://evil.example/roles', ['/roles']))->toBeNull()
        ->and(ReturnPath::sanitize('//evil.example/roles', ['/roles']))->toBeNull()
        ->and(ReturnPath::sanitize('/\\evil.example/roles', ['/roles']))->toBeNull()
        ->and(ReturnPath::sanitize('/users', ['/roles']))->toBeNull()
        ->and(ReturnPath::sanitize('/roles-archive', ['/roles']))->toBeNull()
        ->and(ReturnPath::sanitize('/roles/../users', ['/roles']))->toBeNull();
});

it('rejects a value that is not a bounded string', function (): void {
    expect(ReturnPath::sanitize(null, ['/roles']))->toBeNull()
        ->and(ReturnPath::sanitize(['/roles'], ['/roles']))->toBeNull()
        ->and(ReturnPath::sanitize('/roles/'.str_repeat('a', 2048), ['/roles']))->toBeNull();
});
