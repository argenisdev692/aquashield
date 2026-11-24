<?php

declare(strict_types=1);

namespace Modules\Users\Support;

/**
 * The invitation-link contract, shared by the job, the controllers and the
 * notification. Keeping the TTL out of a job class stops transport code from
 * reaching into the queue layer for a UI value.
 */
final class InvitationLink
{
    public const int EXPIRES_IN_HOURS = 48;
}
