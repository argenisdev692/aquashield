<?php

declare(strict_types=1);

namespace Modules\Users\Enums;

/**
 * The staff account lifecycle.
 *
 * Active  → can sign in and use the workspace.
 * Invited → exists, has no usable credential, must consume the signed link.
 * Suspended → cannot sign in; sessions and API tokens are revoked on entry.
 *
 * The enum is the single server-side definition. The frontend mirrors the three
 * values in the filter and badge components because they are presentation
 * vocabulary, not an authorization rule.
 */
enum UserStatus: string
{
    case Invited = 'invited';
    case Active = 'active';
    case Suspended = 'suspended';

    public function label(): string
    {
        return match ($this) {
            self::Invited => 'Invited',
            self::Active => 'Active',
            self::Suspended => 'Suspended',
        };
    }

    /**
     * Only an active account may authenticate.
     */
    public function canSignIn(): bool
    {
        return $this === self::Active;
    }
}
