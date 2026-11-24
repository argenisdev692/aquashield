<?php

declare(strict_types=1);

namespace Modules\LeadAppointment\Domain\Enums;

/**
 * Field visit lifecycle. Rescheduling is deliberately NOT a status: it records
 * `previous_starts_at`/`previous_ends_at` and returns the row to `scheduled` or
 * `confirmed`, so the calendar never shows a "rescheduled" pseudo-state.
 */
enum AppointmentStatus: string
{
    case Requested = 'requested';
    case Scheduled = 'scheduled';
    case Confirmed = 'confirmed';
    case EnRoute = 'en_route';
    case OnSite = 'on_site';
    case Completed = 'completed';
    case NoShow = 'no_show';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Requested => 'Requested',
            self::Scheduled => 'Scheduled',
            self::Confirmed => 'Confirmed',
            self::EnRoute => 'En route',
            self::OnSite => 'On site',
            self::Completed => 'Completed',
            self::NoShow => 'No show',
            self::Cancelled => 'Cancelled',
        };
    }

    /** Statuses that block a technician's slot. */
    public function isActive(): bool
    {
        return ! in_array($this, [self::Completed, self::NoShow, self::Cancelled], true);
    }

    public function canTransitionTo(self $next): bool
    {
        if ($this === $next) {
            return false;
        }

        return in_array($next, match ($this) {
            self::Requested => [self::Scheduled, self::Cancelled],
            self::Scheduled => [self::Confirmed, self::Cancelled, self::NoShow, self::Scheduled],
            self::Confirmed => [self::EnRoute, self::OnSite, self::Completed, self::NoShow, self::Cancelled, self::Scheduled],
            self::EnRoute => [self::OnSite, self::NoShow, self::Cancelled],
            self::OnSite => [self::Completed, self::Cancelled],
            self::Completed => [],
            self::NoShow => [],
            self::Cancelled => [],
        }, true);
    }
}
