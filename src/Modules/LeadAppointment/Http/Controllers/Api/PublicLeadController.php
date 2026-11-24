<?php

declare(strict_types=1);

namespace Modules\LeadAppointment\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Modules\LeadAppointment\Application\Actions\CaptureLeadAction;
use Modules\LeadAppointment\Application\Dtos\CaptureLeadData;
use Modules\LeadAppointment\Application\Dtos\LeadReceiptData;
use Modules\LeadAppointment\Domain\Enums\LeadSource;
use Modules\LeadAppointment\Http\Requests\StorePublicLeadRequest;
use Spatie\Honeypot\Exceptions\SpamException;
use Spatie\Honeypot\Honeypot;
use Spatie\Honeypot\SpamProtection;

/**
 * Public website intake. Two anti-abuse layers: the honeypot (opt-in, silent
 * drop with the SAME success response) and SpamGuard content scoring inside the
 * action (flags, never rejects). The visitor never learns which layer fired.
 *
 * Scramble documents the request from StorePublicLeadRequest and the response
 * from the simple LeadReceiptData DTO. No auth: rate-limited per IP.
 */
final readonly class PublicLeadController
{
    public function __construct(
        private CaptureLeadAction $captureLead,
        private SpamProtection $spamProtection,
    ) {}

    public function store(StorePublicLeadRequest $request): JsonResponse
    {
        try {
            $this->spamProtection->check($request->all());
        } catch (SpamException) {
            // Indistinguishable from success: a bot must not learn it was caught.
            return response()->json(new LeadReceiptData((string) Str::uuid7()), 201);
        }

        $data = CaptureLeadData::from([
            ...$request->validated(),
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        $lead = $this->captureLead->execute(
            data: $data,
            source: LeadSource::Website,
            sourceDetail: 'Website form',
        );

        return response()->json(new LeadReceiptData($lead->uuid), 201);
    }

    public function honeypot(Honeypot $honeypot): JsonResponse
    {
        return response()->json($honeypot->toArray());
    }
}
