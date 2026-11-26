<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('adds a request id header', function (): void {
    $response = $this->get('/');

    $response->assertOk();

    expect($response->headers->get('X-Request-Id'))
        ->toBeString()
        ->toMatch('/^[0-9a-f-]{36}$/');
});

it('echoes a safe incoming request id', function (): void {
    $response = $this->withHeader('X-Request-Id', 'proxy-abc.123')->get('/');

    expect($response->headers->get('X-Request-Id'))->toBe('proxy-abc.123');
});

it('replaces an unsafe incoming request id', function (): void {
    $response = $this->withHeader('X-Request-Id', 'bad id with spaces')->get('/');

    expect($response->headers->get('X-Request-Id'))->not->toBe('bad id with spaces');
});
