<?php

declare(strict_types=1);

use App\Support\ClientLocation;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

uses(TestCase::class);

it('turns a public ip into a city, region and country label', function (): void {
    Http::fake([
        'https://ipwho.is/*' => Http::response([
            'success' => true,
            'city' => 'Madrid',
            'region' => 'Community of Madrid',
            'country' => 'Spain',
        ]),
    ]);

    $location = app(ClientLocation::class)->forIp('203.0.113.10');

    expect($location)->toBe('Madrid, Community of Madrid, Spain');

    Http::assertSent(fn ($request): bool => $request->url() === 'https://ipwho.is/203.0.113.10');
});

it('never calls the provider for a private or loopback address', function (): void {
    Http::fake();

    expect(app(ClientLocation::class)->forIp('127.0.0.1'))->toBeNull()
        ->and(app(ClientLocation::class)->forIp('10.0.0.5'))->toBeNull();

    Http::assertNothingSent();
});

it('returns null when geolocation is disabled', function (): void {
    config()->set('services.geolocation.enabled', false);
    Http::fake();

    expect(app(ClientLocation::class)->forIp('203.0.113.10'))->toBeNull();

    Http::assertNothingSent();
});
