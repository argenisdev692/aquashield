<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Coarse "where did this sign-in come from" label for security emails.
 *
 * Gmail-style alerts show city/country next to the IP and the device. The
 * lookup is enrichment only: a timeout, a provider error or a private address
 * all return `null` and the email still goes out with the IP and user agent.
 *
 * SSRF notes (OWASP A01): the host is fixed in configuration, never derived
 * from the request; only the address component is appended after
 * `rawurlencode`; redirects are disabled; private, reserved, loopback and
 * link-local ranges are rejected before the call.
 */
final class ClientLocation
{
    public function forIp(?string $ip): ?string
    {
        if (! (bool) config('services.geolocation.enabled', true)) {
            return null;
        }

        if (! is_string($ip) || filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE,
        ) === false) {
            return null;
        }

        $endpoint = trim((string) config('services.geolocation.endpoint', 'https://ipwho.is'));

        if ($endpoint === '') {
            return null;
        }

        try {
            $response = Http::acceptJson()
                ->withOptions(['allow_redirects' => false])
                ->connectTimeout(max(1, (int) config('services.geolocation.connect_timeout', 1)))
                ->timeout(max(1, (int) config('services.geolocation.timeout', 2)))
                ->retry(1, 100, throw: false)
                ->get(rtrim($endpoint, '/').'/'.rawurlencode($ip));

            if (! $response->successful()) {
                return null;
            }

            $payload = $response->json();

            if (! is_array($payload) || ($payload['success'] ?? true) === false) {
                return null;
            }

            $parts = array_values(array_unique(array_filter([
                self::text($payload['city'] ?? null),
                self::text($payload['region'] ?? null),
                self::text($payload['country'] ?? null),
            ], static fn (?string $part): bool => $part !== null && $part !== '')));

            return $parts === [] ? null : implode(', ', $parts);
        } catch (Throwable) {
            // Location is optional context; never block or fail the alert.
            return null;
        }
    }

    private static function text(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }
}
