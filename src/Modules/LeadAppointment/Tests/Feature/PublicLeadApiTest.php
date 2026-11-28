<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\LeadAppointment\Infrastructure\Persistence\Eloquent\Models\Lead;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('accepts a valid public lead and stores the server-side source', function (): void {
    $response = $this->postJson('/api/leads', [
        'first_name' => 'Ada',
        'last_name' => 'Lovelace',
        'email' => 'ADA@AQUASHIELDRESTORATIONUSA.COM',
        'phone' => '(713) 555-0199',
        'address' => '123 Main St',
        'city' => 'Houston',
        'state' => 'TX',
        'zip_code' => '77002',
        'region' => 'houston',
        'damage_description' => 'Water stain on the ceiling.',
        'sms_consent' => false,
    ])->assertCreated();

    $reference = $response->json('reference');
    $lead = Lead::query()->where('uuid', $reference)->firstOrFail();

    expect($lead->source->value)->toBe('website')
        ->and($lead->email)->toBe('ada@aquashieldrestorationusa.com')
        ->and($lead->phone)->toBe('+17135550199')
        ->and($lead->status->value)->toBe('new')
        ->and($lead->is_spam)->toBeFalse();
});

it('rejects a lead without a name or contact', function (): void {
    $this->postJson('/api/leads', [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['first_name', 'last_name', 'email', 'phone']);
});

it('throttles the public lead endpoint', function (): void {
    for ($attempt = 1; $attempt <= 5; $attempt++) {
        $this->postJson('/api/leads', [
            'first_name' => 'Ada',
            'last_name' => 'Lovelace',
            'email' => "ada{$attempt}@aquashieldrestorationusa.com",
            'region' => 'houston',
        ])->assertCreated();
    }

    $this->postJson('/api/leads', [
        'first_name' => 'Ada',
        'last_name' => 'Lovelace',
        'email' => 'ada-over-limit@aquashieldrestorationusa.com',
        'region' => 'houston',
    ])->assertTooManyRequests();
});

it('links a repeat submission instead of hard-blocking it', function (): void {
    $first = $this->postJson('/api/leads', [
        'first_name' => 'Ada',
        'last_name' => 'Lovelace',
        'email' => 'ada@aquashieldrestorationusa.com',
        'phone' => '(713) 555-0199',
        'address' => '123 Main St',
        'city' => 'Houston',
        'state' => 'TX',
        'zip_code' => '77002',
        'region' => 'houston',
    ])->assertCreated();

    $second = $this->postJson('/api/leads', [
        'first_name' => 'Ada',
        'last_name' => 'Lovelace',
        'email' => 'ada@aquashieldrestorationusa.com',
        'phone' => '(713) 555-0199',
        'address' => '123 Main St',
        'city' => 'Houston',
        'state' => 'TX',
        'zip_code' => '77002',
        'region' => 'houston',
    ])->assertCreated();

    $original = Lead::query()->where('uuid', $first->json('reference'))->firstOrFail();
    $duplicate = Lead::query()->where('uuid', $second->json('reference'))->firstOrFail();

    expect($duplicate->duplicate_of_id)->toBe($original->id);
});

it('silently drops a honeypot submission with the same success response', function (): void {
    config([
        'honeypot.randomize_name_field_name' => false,
        'honeypot.name_field_name' => 'my_name',
    ]);

    $this->postJson('/api/leads', [
        'first_name' => 'Bot',
        'last_name' => 'Bot',
        'email' => 'bot@aquashieldrestorationusa.com',
        'region' => 'houston',
        'my_name' => 'filled by a bot',
    ])->assertCreated();

    expect(Lead::query()->count())->toBe(0);
});

it('rejects a client-supplied source', function (): void {
    $this->postJson('/api/leads', [
        'first_name' => 'Ada',
        'last_name' => 'Lovelace',
        'email' => 'ada@aquashieldrestorationusa.com',
        'region' => 'houston',
        'source' => 'meta',
    ])->assertCreated();

    expect(Lead::query()->firstOrFail()->source->value)->toBe('website');
});

it('rejects placeholder and disposable email domains', function (): void {
    foreach (['ada@example.com', 'ada@company.com', 'ada@mailinator.com'] as $email) {
        $this->postJson('/api/leads', [
            'first_name' => 'Ada',
            'last_name' => 'Lovelace',
            'email' => $email,
            'region' => 'houston',
        ])->assertUnprocessable()->assertJsonValidationErrors(['email']);
    }
});

it('accepts a role mailbox but flags it for review', function (): void {
    $response = $this->postJson('/api/leads', [
        'first_name' => 'Ada',
        'last_name' => 'Lovelace',
        'email' => 'info@aquashieldrestorationusa.com',
        'region' => 'houston',
    ])->assertCreated();

    $lead = Lead::query()->where('uuid', $response->json('reference'))->firstOrFail();

    expect($lead->is_spam)->toBeFalse()
        ->and($lead->spam_reasons)->toContain('email:role_address');
});

it('bounds consent metadata to its database columns', function (): void {
    $sourcePage = str_repeat('b', 255);

    $response = $this->withServerVariables(['HTTP_USER_AGENT' => str_repeat('a', 600)])
        ->postJson('/api/leads', [
            'first_name' => 'Ada',
            'last_name' => 'Lovelace',
            'email' => 'ada-metadata@aquashieldrestorationusa.com',
            'sms_consent' => true,
            'consent_text_version' => 'sms-v-test',
            'source_page' => $sourcePage,
        ])->assertCreated();

    $lead = Lead::query()->where('uuid', $response->json('reference'))->firstOrFail();

    expect(mb_strlen($lead->sms_consent_user_agent))->toBe(500)
        ->and($lead->sms_consent_source)->toBe('website')
        ->and($lead->attribution['source_page'])->toBe($sourcePage);
});

it('uses server-controlled consent evidence instead of client claims', function (): void {
    config(['lead-appointment.sms_consent_version' => 'sms-v-server']);

    $response = $this->postJson('/api/leads', [
        'first_name' => 'Ada',
        'last_name' => 'Lovelace',
        'email' => 'ada-consent@aquashieldrestorationusa.com',
        'sms_consent' => true,
        'consent_text_version' => 'forged-version',
        'source_page' => '/forged-source',
    ])->assertCreated();

    $lead = Lead::query()->where('uuid', $response->json('reference'))->firstOrFail();

    expect($lead->consent_text_version)->toBe('sms-v-server')
        ->and($lead->sms_consent_source)->toBe('website')
        ->and($lead->attribution['source_page'])->toBe('/forged-source');
});

it('rejects a consent source page longer than its database column', function (): void {
    $this->postJson('/api/leads', [
        'first_name' => 'Ada',
        'last_name' => 'Lovelace',
        'email' => 'ada-long-source@aquashieldrestorationusa.com',
        'sms_consent' => true,
        'consent_text_version' => 'sms-v-test',
        'source_page' => str_repeat('b', 256),
    ])->assertUnprocessable()->assertJsonValidationErrors(['source_page']);
});

it('accepts yes/no booleans and defaults the SMS consent version', function (): void {
    config(['lead-appointment.sms_consent_version' => 'sms-v-test']);

    $response = $this->postJson('/api/leads', [
        'first_name' => 'Ada',
        'last_name' => 'Lovelace',
        'phone' => '(713) 555-0199',
        'email' => 'ada@aquashieldrestorationusa.com',
        'region' => 'houston',
        'insurance_property' => 'Yes',
        'sms_consent' => 'Yes',
    ])->assertCreated();

    $lead = Lead::query()->where('uuid', $response->json('reference'))->firstOrFail();

    expect($lead->insurance_property)->toBeTrue()
        ->and($lead->sms_consent)->toBeTrue()
        ->and($lead->consent_text_version)->toBe('sms-v-test');
});
