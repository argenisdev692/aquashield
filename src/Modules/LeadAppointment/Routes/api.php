<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\LeadAppointment\Http\Controllers\Api\MetaWebhookController;
use Modules\LeadAppointment\Http\Controllers\Api\PublicLeadController;

/*
| Public API. Stateless (`api` middleware), documented by Scramble under /api.
| The lead endpoint is tightly throttled per IP; the Meta webhook is provider
| traffic and is hidden from the consumer docs.
*/
Route::middleware('api')->prefix('api')->group(function (): void {
    Route::post('/leads', [PublicLeadController::class, 'store'])
        ->middleware('throttle:public-leads')
        ->name('api.leads.store');

    Route::get('/leads/honeypot', [PublicLeadController::class, 'honeypot'])
        ->middleware('throttle:'.config('lead-appointment.public.honeypot_throttle_per_minute', 30).',1')
        ->name('api.leads.honeypot');

    Route::get('/meta/webhook', [MetaWebhookController::class, 'verify'])
        ->middleware('throttle:60,1')
        ->name('api.meta.verify');

    Route::post('/meta/webhook', [MetaWebhookController::class, 'receive'])
        ->middleware('throttle:60,1')
        ->name('api.meta.receive');
});
