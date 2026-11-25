<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\LeadAppointment\Application\Actions\IngestMetaLeadAction;
use Modules\LeadAppointment\Application\Ports\LeadSourceGateway;
use Modules\LeadAppointment\Infrastructure\Jobs\IngestMetaLeadJob;
use Modules\LeadAppointment\Infrastructure\Persistence\Eloquent\Models\LeadIngestionEvent;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('redacts a credential echoed by a provider error before persisting it', function (): void {
    $event = LeadIngestionEvent::query()->create([
        'provider' => 'meta',
        'external_id' => '1234567890',
        'payload' => [],
        'status' => 'received',
        'received_at' => now(),
    ]);

    $gateway = Mockery::mock(LeadSourceGateway::class);
    $gateway->shouldReceive('fetch')
        ->once()
        ->andThrow(new RuntimeException('HTTP 500 access_token=super-secret&fields=id'));

    $job = new IngestMetaLeadJob($event->uuid);

    expect(fn () => $job->handle($gateway, app(IngestMetaLeadAction::class)))
        ->toThrow(RuntimeException::class);

    $event->refresh();

    expect($event->status)->toBe('failed')
        ->and($event->last_error)->toContain('[REDACTED]')
        ->and($event->last_error)->not->toContain('super-secret');
});
