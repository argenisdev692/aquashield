<?php

declare(strict_types=1);

use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\LeadAppointment\Application\Actions\IngestMetaLeadAction;
use Modules\LeadAppointment\Application\Dtos\MetaLeadData;
use Modules\LeadAppointment\Application\Ports\LeadSourceGateway;
use Modules\LeadAppointment\Infrastructure\Jobs\IngestMetaLeadJob;
use Modules\LeadAppointment\Infrastructure\Persistence\Eloquent\Models\Lead;
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
        ->and($event->attempts)->toBe(1)
        ->and($event->last_error)->toContain('[REDACTED]')
        ->and($event->last_error)->not->toContain('super-secret');
});

it('recovers an already-created external lead instead of duplicating it', function (): void {
    $lead = Lead::factory()->create([
        'external_source' => 'meta',
        'external_id' => '1234567890',
    ]);
    $event = LeadIngestionEvent::query()->create([
        'provider' => 'meta',
        'external_id' => '1234567890',
        'payload' => [],
        'status' => 'received',
        'received_at' => now(),
    ]);
    $meta = new MetaLeadData(
        externalId: '1234567890',
        firstName: 'Ada',
        lastName: 'Lovelace',
        email: 'ada@example.com',
        phone: null,
        companyName: null,
        address: null,
        city: null,
        state: null,
        zipCode: null,
        message: null,
        campaignId: null,
        adsetId: null,
        adId: null,
        formId: null,
        pageId: null,
        fieldData: [],
    );
    $gateway = Mockery::mock(LeadSourceGateway::class);
    $gateway->shouldReceive('fetch')->once()->with('1234567890')->andReturn($meta);

    (new IngestMetaLeadJob($event->uuid))->handle($gateway, app(IngestMetaLeadAction::class));

    expect($event->refresh()->status)->toBe('processed')
        ->and(Lead::query()->count())->toBe(1)
        ->and($lead->refresh()->external_id)->toBe('1234567890');
});

it('is unique per ingestion event and ignores processed replays', function (): void {
    $event = LeadIngestionEvent::query()->create([
        'provider' => 'meta',
        'external_id' => '1234567890',
        'payload' => [],
        'status' => 'processed',
        'received_at' => now(),
        'processed_at' => now(),
    ]);
    $gateway = Mockery::mock(LeadSourceGateway::class);
    $gateway->shouldNotReceive('fetch');
    $job = new IngestMetaLeadJob($event->uuid);

    expect($job)->toBeInstanceOf(ShouldBeUnique::class)
        ->and($job->uniqueId())->toBe($event->uuid);

    $job->handle($gateway, app(IngestMetaLeadAction::class));

    expect($event->refresh()->attempts)->toBe(0);
});
