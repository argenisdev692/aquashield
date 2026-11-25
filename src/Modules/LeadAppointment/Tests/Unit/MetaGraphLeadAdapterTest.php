<?php

declare(strict_types=1);

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Modules\LeadAppointment\Infrastructure\Meta\MetaGraphLeadAdapter;
use Shared\Infrastructure\Resilience\CircuitBreaker\CircuitBreakerInterface;
use Tests\TestCase;

uses(TestCase::class);

it('encodes the external id before it reaches the Graph path', function (): void {
    config([
        'lead-appointment.meta.page_access_token' => 'test-token',
        'lead-appointment.meta.graph_version' => 'v27.0',
        'lead-appointment.meta.graph_url' => 'https://graph.facebook.com',
    ]);

    Http::fake([
        'graph.facebook.com/*' => Http::response([
            'id' => 'lead-1',
            'field_data' => [],
        ]),
    ]);

    $breaker = Mockery::mock(CircuitBreakerInterface::class);
    $breaker->shouldReceive('call')
        ->once()
        ->andReturnUsing(static fn (string $service, Closure $operation): mixed => $operation());

    (new MetaGraphLeadAdapter($breaker))->fetch('12/34?x=1');

    Http::assertSent(static function (Request $request): bool {
        return str_contains($request->url(), '/v27.0/12%2F34%3Fx%3D1');
    });
});
