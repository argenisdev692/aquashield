<?php

declare(strict_types=1);

namespace Modules\LeadAppointment\Http\Controllers\Api;

use Dedoc\Scramble\Attributes\ExcludeAllRoutesFromDocs;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Modules\LeadAppointment\Application\Ports\LeadSourceGateway;

/**
 * Meta Lead Ads webhook. GET answers the verification challenge; POST verifies
 * X-Hub-Signature-256 with the app secret and hands the payload to the gateway,
 * which persists one idempotent event per `leadgen_id` and queues the Graph
 * fetch. Excluded from the public API docs: it is a provider contract, not a
 * consumer endpoint.
 */
#[ExcludeAllRoutesFromDocs]
final readonly class MetaWebhookController
{
    public function __construct(private LeadSourceGateway $gateway) {}

    public function verify(Request $request): Response
    {
        $mode = (string) ($request->query('hub_mode') ?? $request->query('hub.mode') ?? '');
        $token = (string) ($request->query('hub_verify_token') ?? $request->query('hub.verify_token') ?? '');
        $challenge = (string) ($request->query('hub_challenge') ?? $request->query('hub.challenge') ?? '');
        $expected = (string) config('lead-appointment.meta.verify_token');

        if ($mode !== 'subscribe' || $expected === '' || ! hash_equals($expected, $token)) {
            abort(403);
        }

        return response($challenge, 200)->header('Content-Type', 'text/plain');
    }

    public function receive(Request $request): JsonResponse
    {
        $secret = (string) config('lead-appointment.meta.app_secret');

        if ($secret === '') {
            abort(503, 'Meta app secret is not configured.');
        }

        $signature = (string) $request->header('X-Hub-Signature-256');
        $expected = 'sha256='.hash_hmac('sha256', $request->getContent(), $secret);

        if ($signature === '' || ! hash_equals($expected, $signature)) {
            abort(403);
        }

        /** @var array<string, mixed> $payload */
        $payload = $request->json()->all();

        $this->gateway->registerWebhook($payload);

        // Meta only needs a fast 2xx; the Graph fetch and lead write are queued.
        return response()->json(['received' => true]);
    }
}
