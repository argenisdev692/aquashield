<?php

declare(strict_types=1);

namespace Modules\LeadAppointment\Domain\Enums;

/**
 * Status of the inspection recorded on the lead itself, matching the legacy
 * `inspection_status` column. The appointment has its own, richer lifecycle;
 * this enum is the operator-facing projection.
 */
enum InspectionStatus: string
{
    case Pending = 'pending';
    case Confirmed = 'confirmed';
    case Completed = 'completed';
    case Declined = 'declined';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Confirmed => 'Confirmed',
            self::Completed => 'Completed',
            self::Declined => 'Declined',
        };
    }

    public static function fromAppointment(AppointmentStatus $status): self
    {
        return match ($status) {
            AppointmentStatus::Requested, AppointmentStatus::Scheduled => self::Pending,
            AppointmentStatus::Confirmed, AppointmentStatus::EnRoute, AppointmentStatus::OnSite => self::Confirmed,
            AppointmentStatus::Completed => self::Completed,
            AppointmentStatus::Cancelled, AppointmentStatus::NoShow => self::Declined,
        };
    }

    public static function fromLegacy(string $value): self
    {
        return self::from(mb_strtolower(trim($value)));
    }
}
