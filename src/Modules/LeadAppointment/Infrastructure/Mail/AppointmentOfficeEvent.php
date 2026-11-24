<?php

declare(strict_types=1);

namespace Modules\LeadAppointment\Infrastructure\Mail;

/**
 * The three appointment lifecycle moments that produce an office copy. The
 * customer has their own mailables; this enum only drives the internal notice.
 */
enum AppointmentOfficeEvent: string
{
    case Scheduled = 'scheduled';
    case Rescheduled = 'rescheduled';
    case Cancelled = 'cancelled';

    public function heading(): string
    {
        return match ($this) {
            self::Scheduled => 'New appointment scheduled',
            self::Rescheduled => 'Appointment rescheduled',
            self::Cancelled => 'Appointment cancelled',
        };
    }

    public function preheader(): string
    {
        return match ($this) {
            self::Scheduled => 'A new inspection was booked. Review the visit details.',
            self::Rescheduled => 'The appointment moved to a new time.',
            self::Cancelled => 'The appointment was cancelled and the slot is free again.',
        };
    }

    public function intro(): string
    {
        return match ($this) {
            self::Scheduled => 'A new appointment was scheduled. The visit details are below.',
            self::Rescheduled => 'An appointment was rescheduled. The new time is below.',
            self::Cancelled => 'An appointment was cancelled. The slot is available again.',
        };
    }
}
