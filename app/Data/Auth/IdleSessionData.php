<?php

declare(strict_types=1);

namespace App\Data\Auth;

use Spatie\LaravelData\Data;

/**
 * Idle-session contract shared with every authenticated Inertia page.
 *
 * The server owns the policy (`config/auth.php` → `hardening.idle`), the
 * client only runs the countdown. Property names are camelCase on the wire
 * because this DTO is read by TypeScript, never submitted as a payload; the
 * generated `generated.d.ts` names the fields exactly as written here.
 */
final class IdleSessionData extends Data
{
    public function __construct(
        /** Seconds of inactivity before the warning dialog appears. */
        public readonly int $warnAfterSeconds,
        /** Seconds the user has to answer before the client signs out. */
        public readonly int $graceSeconds,
        /** Route declared in `routes/web.php`; the client never hardcodes it. */
        public readonly string $keepAliveEndpoint,
        /** Fortify's logout route, resolved through `route('logout')`. */
        public readonly string $logoutEndpoint,
    ) {}
}
