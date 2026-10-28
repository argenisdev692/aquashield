<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Str;

/**
 * The request fingerprint attached to auth audit rows and security emails.
 *
 * Queue workers have no meaningful client request, so they get no IP/UA
 * rather than a loopback address that would pollute the audit trail.
 */
final class AuthRequestContext
{
    /**
     * @return array{ip_address?: string, user_agent?: string}
     */
    public static function properties(): array
    {
        if (app()->runningInConsole() && ! app()->runningUnitTests()) {
            return [];
        }

        return [
            'ip_address' => request()->ip(),
            'user_agent' => Str::limit((string) request()->userAgent(), 255, ''),
        ];
    }
}
