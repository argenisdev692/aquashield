<?php

declare(strict_types=1);

namespace Modules\LeadAppointment\Infrastructure\Meta;

use Illuminate\Support\Facades\Http;
use Modules\LeadAppointment\Application\Dtos\MetaLeadData;
use Modules\LeadAppointment\Application\Ports\LeadSourceGateway;
use Modules\LeadAppointment\Domain\Exceptions\MetaLeadFetchException;
use Modules\LeadAppointment\Infrastructure\Jobs\IngestMetaLeadJob;
use Modules\LeadAppointment\Infrastructure\Persistence\Eloquent\Models\LeadIngestionEvent;
use Shared\Infrastructure\Resilience\CircuitBreaker\CircuitBreakerInterface;
use Throwable;

/**
 * Meta Lead Ads adapter. The webhook only notifies; the full lead must be
 * fetched from Graph v27.0. `registerWebhook()` persists one idempotent event
 * per `leadgen_id` and queues the fetch, so a Graph outage never loses a lead.
 */
final readonly class MetaGraphLeadAdapter implements LeadSourceGateway
{
    private const string SERVICE_KEY = 'meta-graph';

    public function __construct(private CircuitBreakerInterface $breaker) {}

    public function registerWebhook(array $payload): void
    {
        $entries = $payload['entry'] ?? [];

        if (! is_array($entries)) {
            return;
        }

        foreach ($entries as $entry) {
            if (! is_array($entry) || ! is_array($entry['changes'] ?? null)) {
                continue;
            }

            foreach ($entry['changes'] as $change) {
                if (! is_array($change) || ($change['field'] ?? null) !== 'leadgen') {
                    continue;
                }

                $value = $change['value'] ?? null;

                if (! is_array($value)) {
                    continue;
                }

                $leadgenId = $value['leadgen_id'] ?? null;

                if (! is_int($leadgenId) && ! is_string($leadgenId)) {
                    continue;
                }

                $event = LeadIngestionEvent::query()->firstOrCreate(
                    ['provider' => 'meta', 'external_id' => (string) $leadgenId],
                    ['payload' => $value, 'status' => 'received', 'received_at' => now()],
                );

                if ($event->status !== 'processed') {
                    IngestMetaLeadJob::dispatch($event->uuid)->afterCommit();
                }
            }
        }
    }

    public function fetch(string $externalId): MetaLeadData
    {
        $token = (string) config('lead-appointment.meta.page_access_token');
        $version = (string) config('lead-appointment.meta.graph_version');
        $base = (string) config('lead-appointment.meta.graph_url');

        if ($token === '' || $version === '' || $base === '') {
            throw MetaLeadFetchException::unavailable('Meta Graph credentials are not configured.');
        }

        $url = rtrim($base, '/').'/'.$version.'/'.$externalId;

        /** @var array<string, mixed> $payload */
        $payload = $this->breaker->call(
            self::SERVICE_KEY,
            function () use ($url, $token): array {
                $response = Http::timeout(10)
                    ->retry(3, 100, throw: false)
                    ->get($url, [
                        'fields' => 'id,created_time,field_data,ad_id,form_id,campaign_id,adset_id,page_id',
                        'access_token' => $token,
                    ]);

                if ($response->failed()) {
                    throw MetaLeadFetchException::unavailable('HTTP '.$response->status());
                }

                $json = $response->json();

                if (! is_array($json)) {
                    throw MetaLeadFetchException::unavailable('Unexpected Graph response.');
                }

                return $json;
            },
            function (Throwable $exception): array {
                throw MetaLeadFetchException::unavailable($this->redact($exception->getMessage()));
            },
        );

        $fields = $this->fieldMap($payload['field_data'] ?? null);
        $fullName = trim((string) ($fields['full_name'] ?? ''));
        $parts = $fullName === '' ? [] : (preg_split('/\s+/', $fullName, 2) ?: []);

        return new MetaLeadData(
            externalId: (string) ($payload['id'] ?? $externalId),
            firstName: (string) ($fields['first_name'] ?? ($parts[0] ?? 'Lead')),
            lastName: (string) ($fields['last_name'] ?? ($parts[1] ?? '')),
            email: $this->nullableString($fields['email'] ?? null),
            phone: $this->nullableString($fields['phone_number'] ?? $fields['phone'] ?? null),
            companyName: $this->nullableString($fields['company_name'] ?? null),
            address: $this->nullableString($fields['street_address'] ?? $fields['address'] ?? null),
            city: $this->nullableString($fields['city'] ?? null),
            state: $this->nullableString($fields['state'] ?? null),
            zipCode: $this->nullableString($fields['zip_code'] ?? $fields['post_code'] ?? $fields['zip'] ?? null),
            message: $this->nullableString($fields['message'] ?? null),
            campaignId: $this->nullableString($payload['campaign_id'] ?? null),
            adsetId: $this->nullableString($payload['adset_id'] ?? null),
            adId: $this->nullableString($payload['ad_id'] ?? null),
            formId: $this->nullableString($payload['form_id'] ?? null),
            pageId: $this->nullableString($payload['page_id'] ?? null),
            fieldData: $fields,
        );
    }

    /**
     * Graph errors can echo the request URL, and the page token travels in the
     * query string. The job persists this message in `last_error`, so the token
     * must never reach the database or the failed-job log.
     */
    private function redact(string $message): string
    {
        return (string) preg_replace('/(access_token=)[^&\s]+/i', '$1[REDACTED]', $message);
    }

    /**
     * @return array<string, string>
     */
    private function fieldMap(mixed $fieldData): array
    {
        if (! is_array($fieldData)) {
            return [];
        }

        $fields = [];

        foreach ($fieldData as $field) {
            if (! is_array($field)) {
                continue;
            }

            $name = $field['name'] ?? null;
            $values = $field['values'] ?? null;

            if (! is_string($name) || ! is_array($values)) {
                continue;
            }

            $value = $values[0] ?? null;

            if (is_string($value) || is_int($value) || is_float($value)) {
                $fields[$name] = (string) $value;
            }
        }

        return $fields;
    }

    private function nullableString(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return is_scalar($value) ? (string) $value : null;
    }
}
