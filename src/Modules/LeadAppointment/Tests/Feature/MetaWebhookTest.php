<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Modules\LeadAppointment\Infrastructure\Persistence\Eloquent\Models\LeadIngestionEvent;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    config([
        'lead-appointment.meta.app_secret' => 'test-secret',
        'lead-appointment.meta.verify_token' => 'verify-me',
        'lead-appointment.meta.page_access_token' => 'test-token',
        'lead-appointment.meta.graph_version' => 'v27.0',
    ]);
});

it('answers the Meta verification challenge', function (): void {
    $this->get('/api/meta/webhook?'.http_build_query([
        'hub_mode' => 'subscribe',
        'hub_verify_token' => 'verify-me',
        'hub_challenge' => 'challenge-123',
    ]))
        ->assertOk()
        ->assertSee('challenge-123');
});

it('rejects a wrong verification token', function (): void {
    $this->get('/api/meta/webhook?'.http_build_query([
        'hub_mode' => 'subscribe',
        'hub_verify_token' => 'wrong',
        'hub_challenge' => 'challenge-123',
    ]))->assertForbidden();
});

it('accepts a signed webhook and persists one idempotent event', function (): void {
    Queue::fake();

    $payload = [
        'object' => 'page',
        'entry' => [[
            'id' => 'page-1',
            'changes' => [[
                'field' => 'leadgen',
                'value' => [
                    'leadgen_id' => '1234567890',
                    'page_id' => 'page-1',
                    'form_id' => 'form-1',
                    'ad_id' => 'ad-1',
                    'created_time' => 1770000000,
                ],
            ]],
        ]],
    ];

    $body = json_encode($payload, JSON_THROW_ON_ERROR);
    $signature = 'sha256='.hash_hmac('sha256', $body, 'test-secret');

    $this->call('POST', '/api/meta/webhook', [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_ACCEPT' => 'application/json',
        'HTTP_X_HUB_SIGNATURE_256' => $signature,
    ], $body)->assertOk()->assertJsonPath('received', true);

    expect(LeadIngestionEvent::query()->count())->toBe(1);
});

it('rejects a webhook with a bad signature', function (): void {
    $body = json_encode(['object' => 'page', 'entry' => []], JSON_THROW_ON_ERROR);

    $this->call('POST', '/api/meta/webhook', [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_ACCEPT' => 'application/json',
        'HTTP_X_HUB_SIGNATURE_256' => 'sha256=bad',
    ], $body)->assertForbidden();
});
